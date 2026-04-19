<?php

use App\Http\Controllers\Web\GitHubAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/github/redirect', [GitHubAuthController::class, 'redirect'])
    ->name('auth.github.redirect');

Route::get('/auth/github/callback', [GitHubAuthController::class, 'callback'])
    ->name('auth.github.callback');

Route::view('/{any?}', 'welcome')
    ->where('any', '^(?!api|up|auth).*$');
