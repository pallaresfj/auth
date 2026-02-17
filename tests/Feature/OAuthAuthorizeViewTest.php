<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class OAuthAuthorizeViewTest extends TestCase
{
    public function test_authorize_view_renders_scope_cards_and_actions(): void
    {
        $client = new class
        {
            public string $name = 'SILO';

            public function getKey(): string
            {
                return 'client-123';
            }
        };

        $scopes = [
            (object) ['id' => 'openid', 'description' => ''],
            (object) ['id' => 'email', 'description' => ''],
            (object) ['id' => 'profile', 'description' => ''],
        ];

        $request = Request::create('/oauth/authorize', 'GET', [
            'state' => 'state-123',
            'nonce' => 'nonce-123',
        ]);

        $html = view('auth.oauth.authorize', [
            'client' => $client,
            'user' => null,
            'scopes' => $scopes,
            'request' => $request,
            'authToken' => 'auth-token-123',
        ])->render();

        $this->assertStringContainsString('Permisos solicitados', $html);
        $this->assertStringContainsString('Autenticacion OpenID Connect', $html);
        $this->assertStringContainsString('Acceso a correo electronico', $html);
        $this->assertStringContainsString('Acceso a perfil basico', $html);
        $this->assertStringContainsString('Autorizar', $html);
        $this->assertStringContainsString('Cancelar', $html);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $html);
        $this->assertStringContainsString(route('passport.authorizations.approve').'?nonce=nonce-123', $html);
        $this->assertStringContainsString(route('passport.authorizations.deny'), $html);
    }
}
