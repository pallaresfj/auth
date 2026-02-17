<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\AuditPassportFlow::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureGoogleSessionIsAlive::class);
        $middleware->validateCsrfTokens(except: [
            'admin/logout',
        ]);

        $middleware->alias([
            'openid.scope' => \App\Http\Middleware\EnsureOpenIdScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
