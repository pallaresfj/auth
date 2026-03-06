<?php

namespace Tests\Feature;

use App\Filament\Resources\OAuthClients\OAuthClientResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OAuthClientResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_client_accepts_string_inputs_for_redirect_uris_and_scopes(): void
    {
        $client = OAuthClientResource::createClient([
            'name' => 'Cliente de prueba',
            'redirect_uris' => "http://localhost/callback,\nhttps://silo.asyservicios.com/sso/callback",
            'scopes' => "openid,email\nprofile",
            'revoked' => false,
        ]);

        $this->assertSame(
            ['http://localhost/callback', 'https://silo.asyservicios.com/sso/callback'],
            $client->redirect_uris,
        );
        $this->assertSame(['openid', 'email', 'profile'], $client->scopes);

        $this->assertDatabaseHas('oauth_clients', [
            'id' => $client->getKey(),
            'name' => 'Cliente de prueba',
            'revoked' => false,
        ]);
    }

    public function test_delete_client_removes_related_tokens_and_auth_codes(): void
    {
        $client = OAuthClientResource::createClient([
            'name' => 'Cliente para eliminar',
            'redirect_uris' => ['http://localhost/callback'],
            'scopes' => ['openid', 'email', 'profile'],
            'revoked' => false,
        ]);

        DB::table('oauth_access_tokens')->insert([
            'id' => 'access-token-1',
            'user_id' => null,
            'client_id' => $client->getKey(),
            'name' => 'Token prueba',
            'scopes' => json_encode(['openid']),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        DB::table('oauth_refresh_tokens')->insert([
            'id' => 'refresh-token-1',
            'access_token_id' => 'access-token-1',
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        DB::table('oauth_auth_codes')->insert([
            'id' => 'auth-code-1',
            'user_id' => 1,
            'client_id' => $client->getKey(),
            'scopes' => json_encode(['openid']),
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        $deleted = OAuthClientResource::deleteClient($client->fresh());

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('oauth_clients', ['id' => $client->getKey()]);
        $this->assertDatabaseMissing('oauth_access_tokens', ['id' => 'access-token-1']);
        $this->assertDatabaseMissing('oauth_refresh_tokens', ['id' => 'refresh-token-1']);
        $this->assertDatabaseMissing('oauth_auth_codes', ['id' => 'auth-code-1']);
    }

    public function test_generate_frontchannel_secret_entry_uses_valid_format(): void
    {
        $entry = OAuthClientResource::generateFrontchannelSecretEntry('Planes-App');

        [$clientKey, $secret] = explode('|', $entry, 2);

        $this->assertSame('planes-app', $clientKey);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $secret);
    }

    public function test_generate_frontchannel_secret_entry_rejects_invalid_key(): void
    {
        $this->expectException(ValidationException::class);

        OAuthClientResource::generateFrontchannelSecretEntry('planes app');
    }
}
