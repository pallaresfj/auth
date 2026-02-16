<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UserInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_userinfo_returns_profile_when_token_has_openid_scope(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        Passport::actingAs($user, ['openid']);

        $this->getJson('/oauth/userinfo')
            ->assertOk()
            ->assertJson([
                'sub' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => true,
            ]);
    }

    public function test_userinfo_rejects_token_without_openid_scope(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        Passport::actingAs($user, ['profile']);

        $this->getJson('/oauth/userinfo')->assertForbidden();
    }

    public function test_userinfo_rejects_inactive_users(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        Passport::actingAs($user, ['openid']);

        $this->getJson('/oauth/userinfo')->assertForbidden();
    }
}
