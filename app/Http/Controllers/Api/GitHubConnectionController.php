<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AnalysisRunResource;
use App\Models\GitHubConnection;
use App\Services\Analysis\AnalysisAccessService;
use App\Services\Analysis\GrowthAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubConnectionController extends ApiController
{
    public function sync(
        Request $request,
        GitHubConnection $githubConnection,
        GrowthAnalysisService $growthAnalysisService,
        AnalysisAccessService $analysisAccessService,
    ): JsonResponse
    {
        if (! $analysisAccessService->connectionIsAccessible($request, $githubConnection)) {
            return $this->notFound('No analysis run found for this GitHub connection.');
        }

        $analysis = $growthAnalysisService->syncConnection($githubConnection);

        return $this->resource(AnalysisRunResource::make($analysis));
    }

    public function latestAnalysis(
        Request $request,
        GitHubConnection $githubConnection,
        GrowthAnalysisService $growthAnalysisService,
        AnalysisAccessService $analysisAccessService,
    ): JsonResponse
    {
        if (! $analysisAccessService->connectionIsAccessible($request, $githubConnection)) {
            return $this->notFound('No analysis run found for this GitHub connection.');
        }

        $analysis = $growthAnalysisService->latestForConnection($githubConnection);

        if ($analysis === null) {
            return $this->notFound('No analysis run found for this GitHub connection.');
        }

        return $this->resource(AnalysisRunResource::make($analysis));
    }
}
