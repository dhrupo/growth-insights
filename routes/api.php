<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\GitHubConnectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('github')->group(function (): void {
    Route::post('connections', [GitHubConnectionController::class, 'store'])
        ->name('github.connections.store');

    Route::post('connections/{githubConnection}/sync', [GitHubConnectionController::class, 'sync'])
        ->name('github.connections.sync');
});

Route::prefix('analysis')->group(function (): void {
    Route::get('latest', [AnalysisController::class, 'latest'])
        ->name('analysis.latest');

    Route::get('{analysisRun}', [AnalysisController::class, 'show'])
        ->name('analysis.show');

    Route::get('{analysisRun}/timeline', [AnalysisController::class, 'timeline'])
        ->name('analysis.timeline');

    Route::get('{analysisRun}/recommendations', [AnalysisController::class, 'recommendations'])
        ->name('analysis.recommendations');
});
