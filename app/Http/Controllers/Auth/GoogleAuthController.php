<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

class GoogleAuthController extends Controller
{
    private const SESSION_CHECK_IN_PROGRESS = 'google_session_check.in_progress';

    private const SESSION_CHECK_STARTED_AT = 'google_session_check.started_at';

    private const SESSION_CHECK_RETURN_TO = 'google_session_check.return_to';

    private const SESSION_CHECK_LAST_AT = 'google_session_check.last_checked_at';

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function login(): RedirectResponse
    {
        return redirect()->route('auth.google.redirect');
    }

    public function redirectToGoogle(): RedirectResponse
    {
        return $this->buildGoogleDriver('select_account', $this->resolveGoogleCallbackUrl())->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            $this->auditLogger->log('login_google', 'failed', null, null, [
                'reason' => 'google_callback_error',
                'message' => $exception->getMessage(),
            ]);

            return redirect('/')->with('error', 'No fue posible autenticar con Google.');
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));

        if (! $this->isInstitutionalEmail($email)) {
            $this->auditLogger->log('login_google', 'failed', null, null, [
                'email' => $email,
                'reason' => 'email_domain_not_allowed',
            ]);

            return redirect('/')->with('error', 'Acceso denegado: se requiere correo institucional.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $googleUser->getName() ?: $user->name ?: $email;
        $user->google_id = $googleUser->getId();

        $avatarUrl = trim((string) ($googleUser->getAvatar() ?: data_get($googleUser, 'user.picture', '')));
        $user->google_avatar_url = filter_var($avatarUrl, FILTER_VALIDATE_URL) ? $avatarUrl : $user->google_avatar_url;

        if (! $user->exists) {
            $user->is_active = true;
        }

        $user->last_login_at = now();
        $user->save();

        if (! $user->is_active) {
            $this->auditLogger->log('login_google', 'failed', $user, null, [
                'reason' => 'user_inactive',
                'email' => $email,
            ]);

            return redirect('/')->with('error', 'Tu cuenta está inactiva. Contacta al administrador.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_CHECK_LAST_AT, now()->timestamp);

        $this->auditLogger->log('login_google', 'success', $user, null, [
            'email' => $email,
        ]);

        if ($user->isSuperAdmin()) {
            return redirect()->intended('/admin');
        }

        $intended = (string) $request->session()->get('url.intended', '');
        $wantsAdminPanel = str_contains($intended, '/admin');

        if ($wantsAdminPanel) {
            return redirect()
                ->route('home', ['access' => 'denied'])
                ->with('error', 'Tu cuenta no tiene acceso al panel administrativo.');
        }

        return redirect()->intended(route('home'));
    }

    public function startSessionCheck(Request $request): RedirectResponse
    {
        if (! config('sso.google_session_check_enabled', true)) {
            return redirect()->to($this->pullSessionCheckReturnTo($request) ?? route('home'));
        }

        if (! Auth::guard('web')->check()) {
            return redirect()->route('login');
        }

        $request->session()->put(self::SESSION_CHECK_IN_PROGRESS, true);
        $request->session()->put(self::SESSION_CHECK_STARTED_AT, now()->timestamp);

        return $this
            ->buildGoogleDriver('none', $this->resolveGoogleSessionCheckCallbackUrl())
            ->redirect();
    }

    public function completeSessionCheck(Request $request): RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_CHECK_IN_PROGRESS, false)) {
            return redirect()->to(route('home'));
        }

        if ($request->filled('error')) {
            $errorCode = trim((string) $request->query('error'));

            if (in_array($errorCode, ['login_required', 'interaction_required', 'access_denied'], true)) {
                return $this->forceLogoutAfterSessionCheckFailure($request);
            }

            $returnTo = $this->pullSessionCheckReturnTo($request) ?? route('home');
            $this->clearSessionCheckState($request);

            return redirect()->to($returnTo);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->resolveGoogleSessionCheckCallbackUrl())
                ->user();
        } catch (Throwable) {
            return $this->forceLogoutAfterSessionCheckFailure($request);
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));

        if (! $this->isInstitutionalEmail($email)) {
            return $this->forceLogoutAfterSessionCheckFailure($request);
        }

        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (! $user || ! hash_equals(mb_strtolower($user->email), $email)) {
            return $this->forceLogoutAfterSessionCheckFailure($request);
        }

        $name = trim((string) $googleUser->getName());
        if ($name !== '') {
            $user->name = $name;
        }

        $avatarUrl = trim((string) ($googleUser->getAvatar() ?: data_get($googleUser, 'user.picture', '')));

        if (filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            $user->google_avatar_url = $avatarUrl;
        }

        $user->last_login_at = now();
        $user->save();

        $request->session()->put(self::SESSION_CHECK_LAST_AT, now()->timestamp);
        $returnTo = $this->pullSessionCheckReturnTo($request) ?? route('home');

        $this->clearSessionCheckState($request);

        return redirect()->to($returnTo);
    }

    public function logout(Request $request): RedirectResponse
    {
        $source = trim((string) $request->input('source', ''));

        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $this->auditLogger->log('logout', 'success', $user, null, array_filter([
                'source' => $source !== '' ? $source : null,
            ]));
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $fallbackUrl = route('home', ['logged_out' => 1]);
        $continueUrl = $this->resolveContinueUrl($request);
        $targetUrl = $continueUrl ?? $fallbackUrl;
        $sourceClient = $this->resolveSourceClient($source);
        $targetAfterLocalClients = $targetUrl;

        if (config('sso.google_logout_from_browser', true)) {
            $targetAfterLocalClients = $this->buildGoogleLogoutUrl($targetUrl);
        }

        $frontchannelLogoutUrl = $this->buildFrontchannelLogoutUrl($targetAfterLocalClients, $sourceClient);
        $redirectUrl = $frontchannelLogoutUrl ?? $targetAfterLocalClients;

        $response = redirect()->away($redirectUrl);

        $response->withCookie(Cookie::forget(
            config('session.cookie'),
            config('session.path', '/'),
            config('session.domain')
        ));

        return $response;
    }

    private function forceLogoutAfterSessionCheckFailure(Request $request): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = redirect()
            ->route('home')
            ->with('error', 'Se cerró la sesión local porque la sesión de Google ya no está activa.');

        $response->withCookie(Cookie::forget(
            config('session.cookie'),
            config('session.path', '/'),
            config('session.domain')
        ));

        return $response;
    }

    private function buildGoogleDriver(string $prompt, string $redirectUrl): GoogleProvider
    {
        $domains = config('sso.institution_email_domains', []);

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        $driver
            ->redirectUrl($redirectUrl)
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => $prompt]);

        if (! empty($domains)) {
            $driver->with(['hd' => $domains[0]]);
        }

        return $driver;
    }

    private function buildGoogleLogoutUrl(string $continueUrl): string
    {
        return 'https://accounts.google.com/Logout?continue='
            .urlencode('https://appengine.google.com/_ah/logout?continue='.urlencode($continueUrl));
    }

    private function pullSessionCheckReturnTo(Request $request): ?string
    {
        $returnTo = trim((string) $request->session()->pull(self::SESSION_CHECK_RETURN_TO, ''));

        return $returnTo !== '' ? $returnTo : null;
    }

    private function clearSessionCheckState(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_CHECK_IN_PROGRESS,
            self::SESSION_CHECK_STARTED_AT,
            self::SESSION_CHECK_RETURN_TO,
        ]);
    }

    private function resolveContinueUrl(Request $request): ?string
    {
        $continue = trim((string) $request->input('continue', ''));

        if ($continue === '') {
            return null;
        }

        if (str_starts_with($continue, '/')) {
            return url($continue);
        }

        $parts = parse_url($continue);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = mb_strtolower((string) $parts['scheme']);
        $host = mb_strtolower((string) $parts['host']);
        $allowedHosts = config('sso.post_logout_redirect_hosts', []);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (! in_array($host, $allowedHosts, true)) {
            return null;
        }

        return $continue;
    }

    private function resolveSourceClient(string $source): ?string
    {
        $normalized = mb_strtolower(trim($source));

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9-]+/', $normalized, $matches) !== 1) {
            return null;
        }

        return $matches[0] ?? null;
    }

    private function buildFrontchannelLogoutUrl(string $targetUrl, ?string $sourceClient): ?string
    {
        /** @var array<string, string> $clients */
        $clients = config('sso.frontchannel_logout_clients', []);
        /** @var array<string, string> $secrets */
        $secrets = config('sso.frontchannel_logout_secrets', []);
        $timestamp = now()->timestamp;
        $next = $targetUrl;
        $used = false;

        foreach (array_reverse($clients, true) as $client => $logoutUrl) {
            $clientKey = mb_strtolower(trim((string) $client));
            $logoutEndpoint = trim((string) $logoutUrl);

            if ($clientKey === '') {
                continue;
            }

            if ($sourceClient !== null && hash_equals($sourceClient, $clientKey)) {
                continue;
            }

            $secret = trim((string) ($secrets[$clientKey] ?? ''));

            if ($logoutEndpoint === '' || $secret === '') {
                Log::warning('Skipping frontchannel logout client due to missing endpoint or secret.', [
                    'client' => $clientKey,
                    'endpoint_set' => $logoutEndpoint !== '',
                    'secret_set' => $secret !== '',
                ]);

                continue;
            }

            if (! $this->isValidFrontchannelEndpoint($logoutEndpoint)) {
                Log::warning('Skipping frontchannel logout client due to invalid endpoint URL.', [
                    'client' => $clientKey,
                    'endpoint' => $logoutEndpoint,
                ]);

                continue;
            }

            $signature = hash_hmac('sha256', $clientKey.'|'.$timestamp.'|'.$next, $secret);
            $next = $this->appendQuery($logoutEndpoint, [
                'client' => $clientKey,
                'ts' => (string) $timestamp,
                'next' => $next,
                'sig' => $signature,
            ]);
            $used = true;
        }

        return $used ? $next : null;
    }

    private function isValidFrontchannelEndpoint(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = mb_strtolower((string) $parts['scheme']);
        $host = mb_strtolower((string) $parts['host']);
        $allowedHosts = config('sso.post_logout_redirect_hosts', []);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return in_array($host, $allowedHosts, true);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function isInstitutionalEmail(string $email): bool
    {
        if (! str_contains($email, '@')) {
            return false;
        }

        $domains = config('sso.institution_email_domains', []);

        foreach ($domains as $domain) {
            $normalizedDomain = mb_strtolower(trim((string) $domain));

            if ($normalizedDomain !== '' && str_ends_with($email, '@'.$normalizedDomain)) {
                return true;
            }
        }

        return false;
    }

    private function resolveGoogleCallbackUrl(): string
    {
        $configured = trim((string) config('services.google.redirect', ''));

        return $configured !== '' ? $configured : route('auth.google.callback');
    }

    private function resolveGoogleSessionCheckCallbackUrl(): string
    {
        $configured = trim((string) config('services.google.session_check_redirect', ''));

        return $configured !== '' ? $configured : route('auth.google.session-check.callback');
    }
}
