<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DashboardGitHubController;
use App\Http\Controllers\Api\GitHubConnectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->group(function (): void {
    Route::get('summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    Route::get('timeline', [DashboardController::class, 'timeline'])->name('dashboard.timeline');
    Route::get('insights', [DashboardController::class, 'insights'])->name('dashboard.insights');
    Route::get('simulator', [DashboardController::class, 'simulator'])->name('dashboard.simulator');
    Route::post('github/public-analysis', [DashboardGitHubController::class, 'publicAnalysis'])->name('dashboard.github.public-analysis');
    Route::post('github/private-connection', [DashboardGitHubController::class, 'privateConnection'])->name('dashboard.github.private-connection');
});

Route::post('analysis/public', [AnalysisController::class, 'storePublic'])
    ->name('analysis.public.store');

Route::get('analysis/latest/by-username/{githubUsername}', [AnalysisController::class, 'latestByUsername'])
    ->name('analysis.latest.by-username');

Route::post('analysis/simulations', [AnalysisController::class, 'simulate'])
    ->name('analysis.simulations.store');

Route::prefix('github')->group(function (): void {
    Route::post('connections', [GitHubConnectionController::class, 'store'])
        ->name('github.connections.store');

    Route::post('connections/{githubConnection}/sync', [GitHubConnectionController::class, 'sync'])
        ->name('github.connections.sync');

    Route::get('connections/{githubConnection}/analysis/latest', [GitHubConnectionController::class, 'latestAnalysis'])
        ->name('github.connections.analysis.latest');
});

Route::prefix('analysis')->group(function (): void {
    Route::get('{analysisRun}', [AnalysisController::class, 'show'])
        ->name('analysis.show');

    Route::get('{analysisRun}/timeline', [AnalysisController::class, 'timeline'])
        ->name('analysis.timeline');

    Route::get('{analysisRun}/recommendations', [AnalysisController::class, 'recommendations'])
        ->name('analysis.recommendations');
});
