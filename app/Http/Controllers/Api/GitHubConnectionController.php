<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AnalysisRunResource;
use App\Models\GitHubConnection;
use App\Services\Analysis\GrowthAnalysisService;
use Illuminate\Http\JsonResponse;

class GitHubConnectionController extends ApiController
{
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
