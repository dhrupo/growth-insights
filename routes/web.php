<?php

use App\Http\Controllers\Web\GitHubAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/dashboard/github')->group(function (): void {
    Route::get('current-analysis', [\App\Http\Controllers\Api\DashboardGitHubController::class, 'currentAnalysis'])
        ->name('dashboard.github.current-analysis');
    Route::post('current-analysis/sync', [\App\Http\Controllers\Api\DashboardGitHubController::class, 'syncCurrentAnalysis'])
        ->name('dashboard.github.current-analysis.sync');
});

Route::get('/auth/github/redirect', [GitHubAuthController::class, 'redirect'])
    ->name('auth.github.redirect');

Route::get('/auth/github/callback', [GitHubAuthController::class, 'callback'])
    ->name('auth.github.callback');

Route::view('/{any?}', 'welcome')
    ->where('any', '^(?!api|up|auth).*$');
