<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Events\AccessTokenCreated;
use Tests\TestCase;

class OAuthAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_failures_are_audited(): void
    {
        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());

        $this->assertDatabaseHas('audit_logins', [
            'event' => 'token_issued',
            'status' => 'failed',
        ]);
    }

    public function test_access_token_created_event_is_audited(): void
    {
        $user = User::factory()->create();

        event(new AccessTokenCreated('token-123', (string) $user->id, 'client-xyz'));

        $this->assertDatabaseHas('audit_logins', [
            'event' => 'token_issued',
            'status' => 'success',
            'user_id' => $user->id,
            'client_id' => 'client-xyz',
        ]);
    }
}
