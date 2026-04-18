<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreGitHubConnectionRequest;
use App\Http\Resources\AnalysisRunResource;
use App\Http\Resources\GitHubConnectionResource;
use App\Models\GitHubConnection;
use App\Services\Analysis\GrowthAnalysisService;
use Illuminate\Http\JsonResponse;

class GitHubConnectionController extends ApiController
{
    public function store(StoreGitHubConnectionRequest $request): JsonResponse
    {
        $connection = GitHubConnection::create([
            'user_id' => $request->validated('user_id'),
            'github_username' => $request->validated('github_username'),
            'analysis_mode' => $request->validated('analysis_mode', 'public_private'),
            'access_token' => $request->validated('access_token'),
            'sync_status' => 'idle',
            'connected_at' => now(),
        ]);

        return $this->resource(GitHubConnectionResource::make($connection), 201);
    }

    public function sync(GitHubConnection $githubConnection, GrowthAnalysisService $growthAnalysisService): JsonResponse
    {
        $analysis = $growthAnalysisService->syncConnection($githubConnection);

        return $this->resource(AnalysisRunResource::make($analysis));
    }

    public function latestAnalysis(GitHubConnection $githubConnection, GrowthAnalysisService $growthAnalysisService): JsonResponse
    {
        $analysis = $growthAnalysisService->latestForConnection($githubConnection);

        if ($analysis === null) {
            return $this->notFound('No analysis run found for this GitHub connection.');
        }

        return $this->resource(AnalysisRunResource::make($analysis));
    }
}
