<?php

namespace App\Http\Controllers\Api;

use App\Services\Analysis\AnalysisAccessService;
use App\Services\Analysis\DashboardPayloadBuilder;
use App\Services\Analysis\GrowthAnalysisService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardGitHubController extends ApiController
{
    public function currentAnalysis(
        Request $request,
        AnalysisAccessService $analysisAccessService,
        GrowthAnalysisService $growthAnalysisService,
        DashboardPayloadBuilder $payloadBuilder,
    ): JsonResponse {
        $connection = $analysisAccessService->currentConnection($request);
        $run = $connection ? $growthAnalysisService->latestForConnection($connection) : null;

        return $this->json($payloadBuilder->currentSession($connection, $run));
    }

    public function syncCurrentAnalysis(
        Request $request,
        AnalysisAccessService $analysisAccessService,
        GrowthAnalysisService $growthAnalysisService,
        DashboardPayloadBuilder $payloadBuilder,
    ): JsonResponse {
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }

        $connection = $analysisAccessService->currentConnection($request);

        if ($connection === null) {
            return response()->json([
                'message' => 'Connect GitHub before running an analysis.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $run = $growthAnalysisService->syncConnection($connection);
        } catch (RequestException $exception) {
            return $this->upstreamFailure($exception, 'GitHub analysis failed.');
        }

        return $this->json($payloadBuilder->workbench($run, $connection));
    }

    public function latestAnalysis(
        Request $request,
        string $githubUsername,
        AnalysisAccessService $analysisAccessService,
        GrowthAnalysisService $growthAnalysisService,
        DashboardPayloadBuilder $payloadBuilder,
    ): JsonResponse {
        $run = $analysisAccessService->latestAccessibleRunForUsername($request, $githubUsername);
        $connection = $analysisAccessService->currentConnection($request);

        if ($connection !== null && $connection->github_username !== $githubUsername) {
            $connection = null;
        }

        if ($run === null) {
            return $this->notFound('No analysis run found for this GitHub username.');
        }

        return $this->json($payloadBuilder->workbench($run, $connection));
    }

    public function publicAnalysis(
        Request $request,
        GrowthAnalysisService $growthAnalysisService,
        DashboardPayloadBuilder $payloadBuilder,
    ): JsonResponse {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:39', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        try {
            $run = $growthAnalysisService->analyzePublicUsername($data['username']);
        } catch (RequestException $exception) {
            return $this->upstreamFailure($exception, 'Public GitHub analysis failed.');
        }

        return $this->json($payloadBuilder->workbench($run));
    }
}
