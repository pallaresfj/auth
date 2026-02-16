<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_rejects_non_institutional_domain(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn('test@gmail.com');
        $googleUser->shouldReceive('getName')->andReturn('Test User');
        $googleUser->shouldReceive('getId')->andReturn('google-non-institutional');

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

    public function test_google_callback_creates_user_and_logs_in(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn('docente@iedagropivijay.edu.co');
        $googleUser->shouldReceive('getName')->andReturn('Docente Test');
        $googleUser->shouldReceive('getId')->andReturn('google-123');

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback')->assertRedirect('/admin');

        $user = User::query()->where('email', 'docente@iedagropivijay.edu.co')->firstOrFail();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->last_login_at);

        $this->assertDatabaseHas('audit_logins', [
            'event' => 'login_google',
            'status' => 'success',
            'user_id' => $user->id,
        ]);
    }
}
