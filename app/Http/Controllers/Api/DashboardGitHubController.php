<?php

namespace App\Http\Controllers\Api;

use App\Models\GitHubConnection;
use App\Services\Analysis\DashboardPayloadBuilder;
use App\Services\Analysis\GrowthAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardGitHubController extends ApiController
{
    public function publicAnalysis(
        Request $request,
        GrowthAnalysisService $growthAnalysisService,
        DashboardPayloadBuilder $payloadBuilder,
    ): JsonResponse {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:39', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        $run = $growthAnalysisService->analyzePublicUsername($data['username']);

        return $this->json($payloadBuilder->workbench($run));
    }

    public function privateConnection(
        Request $request,
        GrowthAnalysisService $growthAnalysisService,
        DashboardPayloadBuilder $payloadBuilder,
    ): JsonResponse {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:39', 'regex:/^[A-Za-z0-9-]+$/'],
            'token' => ['required', 'string', 'min:8'],
        ]);

        $connection = GitHubConnection::query()->updateOrCreate(
            ['github_username' => $data['username'], 'user_id' => null],
            [
                'analysis_mode' => 'public_private',
                'access_token' => $data['token'],
                'sync_status' => 'idle',
                'connected_at' => now(),
            ],
        );

        $run = $growthAnalysisService->syncConnection($connection);

        return $this->json($payloadBuilder->workbench($run, $connection));
    }
}
