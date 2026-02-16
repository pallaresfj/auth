<?php

return [
    'passport' => [
        'tokens_can' => [
            'openid' => 'Enable OpenID Connect authentication',
            'profile' => 'Read access to basic profile information',
            'email' => 'Read access to email information',
            'phone' => 'Read access to phone information',
            'address' => 'Read access to address information',
        ],
    ],

    'custom_claim_sets' => [
        'openid' => [
            'is_active',
        ],
    ],

    'repositories' => [
        'identity' => OpenIDConnect\Repositories\IdentityRepository::class,
    ],

    'routes' => [
        'discovery' => true,
        'jwks' => true,
        'jwks_url' => '/oauth/jwks',
    ],

    'discovery' => [
        'hide_scopes' => false,
    ],

    'signer' => Lcobucci\JWT\Signer\Rsa\Sha256::class,

    'token_headers' => [
        'kid' => env('OIDC_KEY_ID', 'passport-rsa-1'),
    ],

    'use_microseconds' => true,

    'issuedBy' => env('ISSUER', env('APP_URL', 'http://localhost')),

    'forceHttps' => (bool) env('OIDC_FORCE_HTTPS', true),
];
