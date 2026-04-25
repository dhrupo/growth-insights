<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StartPublicAnalysisRequest;
use App\Http\Requests\StoreScoreSimulationRequest;
use App\Http\Resources\AnalysisRecommendationResource;
use App\Http\Resources\AnalysisRunResource;
use App\Http\Resources\AnalysisWeeklyBucketResource;
use App\Models\AnalysisRun;
use App\Services\Analysis\AnalysisAccessService;
use App\Services\Analysis\GrowthAnalysisService;
use App\Services\Analysis\ScoreSimulationService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisController extends ApiController
{
    public function storePublic(StartPublicAnalysisRequest $request, GrowthAnalysisService $growthAnalysisService): JsonResponse
    {
        try {
            $analysis = $growthAnalysisService->analyzePublicUsername($request->validated('github_username'));
        } catch (RequestException $exception) {
            return $this->upstreamFailure($exception, 'Public GitHub analysis failed.');
        }

        return $this->resource(AnalysisRunResource::make($analysis), 201);
    }

    public function latestByUsername(
        Request $request,
        string $githubUsername,
        AnalysisAccessService $analysisAccessService,
    ): JsonResponse
    {
        $analysis = $analysisAccessService->latestAccessibleRunForUsername($request, $githubUsername);

        if ($analysis === null) {
            return $this->notFound('No analysis run found for this GitHub username.');
        }

        return $this->resource(AnalysisRunResource::make($analysis));
    }

    public function show(
        Request $request,
        AnalysisRun $analysisRun,
        AnalysisAccessService $analysisAccessService,
    ): JsonResponse
    {
        $analysisRun = $analysisAccessService->accessibleRun($request, $analysisRun);

        if ($analysisRun === null) {
            return $this->notFound('No analysis run found for this analysis ID.');
        }

        return $this->resource(AnalysisRunResource::make($analysisRun));
    }

    public function timeline(
        Request $request,
        AnalysisRun $analysisRun,
        AnalysisAccessService $analysisAccessService,
    ): JsonResponse
    {
        if (! $analysisAccessService->runIsAccessible($request, $analysisRun)) {
            return $this->notFound('No analysis run found for this analysis ID.');
        }

        return $this->resource(AnalysisWeeklyBucketResource::collection($analysisRun->weeklyBuckets()->orderBy('week_start')->get()));
    }

    public function recommendations(
        Request $request,
        AnalysisRun $analysisRun,
        AnalysisAccessService $analysisAccessService,
    ): JsonResponse
    {
        if (! $analysisAccessService->runIsAccessible($request, $analysisRun)) {
            return $this->notFound('No analysis run found for this analysis ID.');
        }

        return $this->resource(AnalysisRecommendationResource::collection($analysisRun->recommendations()->orderBy('sort_order')->get()));
    }

    public function simulate(StoreScoreSimulationRequest $request, ScoreSimulationService $scoreSimulationService): JsonResponse
    {
        return $this->json($scoreSimulationService->simulate($request->validated()));
    }
}
