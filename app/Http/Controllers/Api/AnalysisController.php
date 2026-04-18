<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StartPublicAnalysisRequest;
use App\Http\Requests\StoreScoreSimulationRequest;
use App\Http\Resources\AnalysisRecommendationResource;
use App\Http\Resources\AnalysisRunResource;
use App\Http\Resources\AnalysisWeeklyBucketResource;
use App\Models\AnalysisRun;
use App\Services\Analysis\GrowthAnalysisService;
use App\Services\Analysis\ScoreSimulationService;
use Illuminate\Http\JsonResponse;

class AnalysisController extends ApiController
{
    public function storePublic(StartPublicAnalysisRequest $request, GrowthAnalysisService $growthAnalysisService): JsonResponse
    {
        $analysis = $growthAnalysisService->analyzePublicUsername($request->validated('github_username'));

        return $this->resource(AnalysisRunResource::make($analysis), 201);
    }

    public function latestByUsername(string $githubUsername, GrowthAnalysisService $growthAnalysisService): JsonResponse
    {
        $analysis = $growthAnalysisService->latestForUsername($githubUsername);

        if ($analysis === null) {
            return $this->notFound('No analysis run found for this GitHub username.');
        }

        return $this->resource(AnalysisRunResource::make($analysis));
    }

    public function show(AnalysisRun $analysisRun): JsonResponse
    {
        $analysisRun->load(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);

        return $this->resource(AnalysisRunResource::make($analysisRun));
    }

    public function timeline(AnalysisRun $analysisRun): JsonResponse
    {
        return $this->resource(AnalysisWeeklyBucketResource::collection($analysisRun->weeklyBuckets()->orderBy('week_start')->get()));
    }

    public function recommendations(AnalysisRun $analysisRun): JsonResponse
    {
        return $this->resource(AnalysisRecommendationResource::collection($analysisRun->recommendations()->orderBy('sort_order')->get()));
    }

    public function simulate(StoreScoreSimulationRequest $request, ScoreSimulationService $scoreSimulationService): JsonResponse
    {
        return $this->json($scoreSimulationService->simulate($request->validated()));
    }
}
