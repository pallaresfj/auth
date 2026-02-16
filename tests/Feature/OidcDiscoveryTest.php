<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OidcDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_endpoint_returns_minimum_fields(): void
    {
        $response = $this->getJson('/.well-known/openid-configuration');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'issuer',
                'authorization_endpoint',
                'token_endpoint',
                'jwks_uri',
                'response_types_supported',
                'subject_types_supported',
                'id_token_signing_alg_values_supported',
                'scopes_supported',
            ]);
    }

    public function test_jwks_endpoint_returns_public_keys(): void
    {
        $response = $this->getJson('/oauth/jwks');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'keys' => [
                    ['alg', 'kty', 'use', 'n', 'e'],
                ],
            ]);
    }
}
