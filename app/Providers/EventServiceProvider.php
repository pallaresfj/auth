<?php

namespace App\Providers;

use App\Listeners\LogAccessTokenCreated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Passport\Events\AccessTokenCreated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        AccessTokenCreated::class => [
            LogAccessTokenCreated::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
