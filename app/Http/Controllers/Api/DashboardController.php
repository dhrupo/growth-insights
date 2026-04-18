<?php

namespace App\Http\Controllers\Api;

use App\Models\AnalysisRun;
use App\Services\Analysis\DashboardPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private readonly DashboardPayloadBuilder $payloadBuilder)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        return $this->json($this->payloadBuilder->summary($run));
    }

    public function timeline(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        return $this->json($this->payloadBuilder->timeline($run));
    }

    public function insights(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        return $this->json($this->payloadBuilder->insights($run));
    }

    public function simulator(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        return $this->json($this->payloadBuilder->simulator($run));
    }

    private function resolveRun(Request $request): ?AnalysisRun
    {
        $query = AnalysisRun::query()
            ->where('status', 'completed')
            ->with(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);

        if ($request->filled('analysis_run_id')) {
            return $query->find($request->integer('analysis_run_id'));
        }

        if ($request->filled('github_username')) {
            $query->where('github_username', $request->string('github_username')->toString());
        }

        return $query->latest('completed_at')->latest('id')->first();
    }
}
