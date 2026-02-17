<?php

namespace Database\Seeders;

use App\Models\OAuthClient;
use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;

class OAuthClientsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var ClientRepository $clients */
        $clients = app(ClientRepository::class);

        $definitions = [
            [
                'name' => 'planes',
                'redirect_uris' => ['https://gestionplanes.test/sso/callback'],
                'scopes' => ['openid', 'email', 'profile'],
            ],
            [
                'name' => 'asistencia',
                'redirect_uris' => ['https://teachingassistance.test/sso/callback'],
                'scopes' => ['openid', 'email', 'profile'],
            ],
            [
                'name' => 'silo',
                'redirect_uris' => ['http://localhost:8000/sso/callback'],
                'scopes' => ['openid', 'email', 'profile'],
            ],
        ];

        foreach ($definitions as $definition) {
            /** @var OAuthClient|null $existing */
            $existing = OAuthClient::query()->where('name', $definition['name'])->first();

            if ($existing) {
                $existing->forceFill([
                    'redirect_uris' => $definition['redirect_uris'],
                    'grant_types' => ['authorization_code', 'refresh_token'],
                    'scopes' => $definition['scopes'],
                    'revoked' => false,
                ])->save();

                continue;
            }

            $client = $clients->createAuthorizationCodeGrantClient(
                name: $definition['name'],
                redirectUris: $definition['redirect_uris'],
                confidential: true,
            );

            $client->forceFill([
                'scopes' => $definition['scopes'],
            ])->save();
        }
    }
}
