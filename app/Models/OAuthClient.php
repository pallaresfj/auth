<?php

namespace App\Models;

use Laravel\Passport\Client;

class OAuthClient extends Client
{
    protected $casts = [
        'grant_types' => 'array',
        'scopes' => 'array',
        'redirect_uris' => 'array',
        'revoked' => 'boolean',
    ];
}
