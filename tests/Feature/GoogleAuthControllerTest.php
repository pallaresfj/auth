<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso.frontchannel_logout_clients', []);
        config()->set('sso.frontchannel_logout_secrets', []);
    }

    public function test_google_callback_rejects_non_institutional_domain(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn('test@gmail.com');
        $googleUser->shouldReceive('getName')->andReturn('Test User');
        $googleUser->shouldReceive('getId')->andReturn('google-non-institutional');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('audit_logins', [
            'event' => 'login_google',
            'status' => 'failed',
        ]);
    }

    public function test_google_callback_creates_user_with_avatar_and_logs_in(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn('docente@iedagropivijay.edu.co');
        $googleUser->shouldReceive('getName')->andReturn('Docente Test');
        $googleUser->shouldReceive('getId')->andReturn('google-123');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://avatars.example.com/docente.jpg');

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback')->assertRedirect(route('home'));

        $user = User::query()->where('email', 'docente@iedagropivijay.edu.co')->firstOrFail();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('https://avatars.example.com/docente.jpg', $user->google_avatar_url);
        $this->assertNotNull($user->last_login_at);

        $this->assertDatabaseHas('audit_logins', [
            'event' => 'login_google',
            'status' => 'success',
            'user_id' => $user->id,
        ]);
    }

    public function test_logout_allows_safe_continue_redirect_host(): void
    {
        config()->set('sso.google_logout_from_browser', false);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $continue = 'http://localhost:8000/admin/login';

        $this->actingAs($user, 'web');

        $this->get('/logout?continue='.urlencode($continue).'&source=silo')
            ->assertRedirect($continue);

        $this->assertGuest('web');
    }

    public function test_logout_ignores_unsafe_continue_redirect_host(): void
    {
        config()->set('sso.google_logout_from_browser', false);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web');

        $this->get('/logout?continue='.urlencode('https://evil.example/logout'))
            ->assertRedirect(route('home', ['logged_out' => 1]));

        $this->assertGuest('web');
    }

    public function test_logout_uses_google_logout_chain_when_enabled(): void
    {
        config()->set('sso.google_logout_from_browser', true);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $continue = 'http://localhost:8000/admin/login';

        $this->actingAs($user, 'web');

        $response = $this->get('/logout?continue='.urlencode($continue));

        $target = $response->headers->get('Location');

        $this->assertNotNull($target);
        $this->assertStringContainsString('https://accounts.google.com/Logout?continue=', (string) $target);
        $this->assertStringContainsString(urlencode('https://appengine.google.com/_ah/logout?continue='.urlencode($continue)), (string) $target);
    }

    public function test_logout_redirects_to_signed_frontchannel_endpoint_when_configured(): void
    {
        config()->set('sso.google_logout_from_browser', false);
        config()->set('sso.frontchannel_logout_clients', [
            'silo' => 'http://localhost:8000/sso/frontchannel-logout',
        ]);
        config()->set('sso.frontchannel_logout_secrets', [
            'silo' => 'shared-secret',
        ]);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $continue = 'http://localhost:8000/admin/login';

        Carbon::setTestNow(Carbon::create(2026, 2, 17, 12, 0, 0));

        try {
            $this->actingAs($user, 'web');
            $response = $this->get('/logout?continue='.urlencode($continue));
        } finally {
            Carbon::setTestNow();
        }

        $target = $response->headers->get('Location');

        $this->assertNotNull($target);

        $parts = parse_url((string) $target);
        parse_str((string) ($parts['query'] ?? ''), $query);

        $origin = ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        $this->assertSame('http://localhost:8000/sso/frontchannel-logout', $origin.($parts['path'] ?? ''));
        $this->assertSame('silo', $query['client'] ?? null);
        $this->assertSame((string) Carbon::create(2026, 2, 17, 12, 0, 0)->timestamp, $query['ts'] ?? null);
        $this->assertSame($continue, $query['next'] ?? null);
        $this->assertSame(
            hash_hmac('sha256', 'silo|'.($query['ts'] ?? '').'|'.$continue, 'shared-secret'),
            $query['sig'] ?? null
        );
    }

    public function test_logout_skips_source_client_in_frontchannel_fanout(): void
    {
        config()->set('sso.google_logout_from_browser', false);
        config()->set('sso.frontchannel_logout_clients', [
            'silo' => 'http://localhost:8000/sso/frontchannel-logout',
        ]);
        config()->set('sso.frontchannel_logout_secrets', [
            'silo' => 'shared-secret',
        ]);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $continue = 'http://localhost:8000/admin/login';

        $this->actingAs($user, 'web');

        $this->get('/logout?continue='.urlencode($continue).'&source=silo_filament')
            ->assertRedirect($continue);
    }

    public function test_logout_fail_open_when_frontchannel_client_secret_is_missing(): void
    {
        config()->set('sso.google_logout_from_browser', false);
        config()->set('sso.frontchannel_logout_clients', [
            'silo' => 'http://localhost:8000/sso/frontchannel-logout',
        ]);
        config()->set('sso.frontchannel_logout_secrets', []);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $continue = 'http://localhost:8000/admin/login';

        $this->actingAs($user, 'web');

        $this->get('/logout?continue='.urlencode($continue))
            ->assertRedirect($continue);
    }

    public function test_logout_sends_frontchannel_first_when_google_logout_is_enabled(): void
    {
        config()->set('sso.google_logout_from_browser', true);
        config()->set('sso.frontchannel_logout_clients', [
            'silo' => 'http://localhost:8000/sso/frontchannel-logout',
        ]);
        config()->set('sso.frontchannel_logout_secrets', [
            'silo' => 'shared-secret',
        ]);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $continue = 'http://localhost:8000/admin/login';

        Carbon::setTestNow(Carbon::create(2026, 2, 17, 12, 0, 0));

        try {
            $this->actingAs($user, 'web');
            $response = $this->get('/logout?continue='.urlencode($continue));
        } finally {
            Carbon::setTestNow();
        }

        $target = $response->headers->get('Location');

        $this->assertNotNull($target);

        $parts = parse_url((string) $target);
        parse_str((string) ($parts['query'] ?? ''), $query);

        $origin = ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        $this->assertSame('http://localhost:8000/sso/frontchannel-logout', $origin.($parts['path'] ?? ''));
        $this->assertSame('silo', $query['client'] ?? null);
        $this->assertSame((string) Carbon::create(2026, 2, 17, 12, 0, 0)->timestamp, $query['ts'] ?? null);

        $expectedGoogleLogoutUrl = 'https://accounts.google.com/Logout?continue='
            .urlencode('https://appengine.google.com/_ah/logout?continue='.urlencode($continue));

        $this->assertSame($expectedGoogleLogoutUrl, $query['next'] ?? null);
        $this->assertSame(
            hash_hmac(
                'sha256',
                'silo|'.($query['ts'] ?? '').'|'.$expectedGoogleLogoutUrl,
                'shared-secret'
            ),
            $query['sig'] ?? null
        );
    }

    public function test_session_check_callback_logs_out_when_google_session_is_missing(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->withSession([
            'google_session_check.in_progress' => true,
            'google_session_check.return_to' => 'http://localhost:9000/admin',
        ])->get('/auth/google/session-check/callback?error=login_required');

        $response
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');

        $this->assertGuest('web');
    }

    public function test_middleware_redirects_authenticated_panel_requests_to_session_check(): void
    {
        config()->set('sso.superadmin_emails', ['admin@iedagropivijay.edu.co']);
        config()->set('sso.google_session_check_enabled', true);
        config()->set('sso.google_session_check_interval_seconds', 60);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->withSession([
            'google_session_check.last_checked_at' => 0,
        ])->get('/admin');

        $response->assertRedirect(route('auth.google.session-check.start'));
    }

    public function test_admin_logout_post_does_not_require_csrf_token(): void
    {
        config()->set('sso.superadmin_emails', ['admin@iedagropivijay.edu.co']);
        config()->set('sso.google_session_check_enabled', false);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@iedagropivijay.edu.co',
            'google_id' => 'google-admin',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->post('/admin/logout');

        $response->assertStatus(302);
        $response->assertRedirect('/logout');
    }
}
