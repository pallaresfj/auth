<?php

namespace App\Providers;

use App\Models\OAuthClient;
use Laravel\Passport\Passport;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::tokensCan(config('openid.passport.tokens_can', []));

        Passport::tokensExpireIn(now()->addMinutes((int) config('sso.token_ttl_minutes', 30)));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('sso.refresh_token_ttl_days', 14)));
        Passport::personalAccessTokensExpireIn(now()->addDays((int) config('sso.refresh_token_ttl_days', 14)));

        Passport::authorizationView('auth.oauth.authorize');
    }
}
