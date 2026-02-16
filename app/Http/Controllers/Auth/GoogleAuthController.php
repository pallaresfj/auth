<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function login(): RedirectResponse
    {
        return redirect()->route('auth.google.redirect');
    }

    public function redirectToGoogle(): RedirectResponse
    {
        $domains = config('sso.institution_email_domains', []);

        $driver = Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with([
                'prompt' => 'select_account',
            ]);

        if (! empty($domains)) {
            $driver->with(['hd' => $domains[0]]);
        }

        return $driver->redirect();
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

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $this->auditLogger->log('logout', 'success', $user);
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home', ['logged_out' => 1]);
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
}
