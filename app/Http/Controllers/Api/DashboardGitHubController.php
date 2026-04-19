<?php

namespace App\Http\Controllers\Api;

use App\Services\Analysis\DashboardPayloadBuilder;
use App\Services\Analysis\GrowthAnalysisService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardGitHubController extends ApiController
{
    public function latestAnalysis(
        string $githubUsername,
        GrowthAnalysisService $growthAnalysisService,
        DashboardPayloadBuilder $payloadBuilder,
    ): JsonResponse {
        $run = $growthAnalysisService->latestForUsername($githubUsername);
        $connection = $growthAnalysisService->connectionForUsername($githubUsername);

        if ($run === null) {
            return $this->notFound('No analysis run found for this GitHub username.');
        }

        return $this->json($payloadBuilder->workbench($run, $connection ?? $run->githubConnection));
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

    private function upstreamFailure(RequestException $exception, string $fallbackMessage): JsonResponse
    {
        $status = $exception->response?->status() ?? Response::HTTP_BAD_GATEWAY;
        $message = $exception->response?->json('message')
            ?? $exception->response?->json('error')
            ?? $fallbackMessage;

        if ($status === Response::HTTP_FORBIDDEN && str_contains(strtolower($message), 'rate limit')) {
            $status = Response::HTTP_TOO_MANY_REQUESTS;
            $message = 'GitHub rate limited this analysis request. Connect GitHub or try again later.';
        }

        return response()->json([
            'message' => $message,
        ], $status);
    }
}
