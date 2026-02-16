<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Oidc\UserInfoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    if ($user instanceof \App\Models\User && $user->is_active && $user->isSuperAdmin()) {
        return redirect('/admin');
    }

    return response()->view('welcome', [], 200);
})->name('home');

Route::get('/login', [GoogleAuthController::class, 'login'])->name('login');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::match(['GET', 'POST'], '/logout', [GoogleAuthController::class, 'logout'])->name('logout');

Route::get('/oauth/userinfo', UserInfoController::class)
    ->middleware(['auth:api', 'openid.scope'])
    ->name('openid.userinfo');
