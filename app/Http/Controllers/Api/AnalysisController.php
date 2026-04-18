<?php

namespace App\Http\Controllers\Api;

use App\Models\AnalysisRun;
use Illuminate\Http\JsonResponse;

class AnalysisController extends ApiController
{
    public function latest(): JsonResponse
    {
        return $this->notImplemented('Latest analysis lookup is scaffolded but not implemented yet.');
    }

    public function show(AnalysisRun $analysisRun): JsonResponse
    {
        return $this->notImplemented('Analysis detail retrieval is scaffolded but not implemented yet.');
    }

    public function timeline(AnalysisRun $analysisRun): JsonResponse
    {
        return $this->notImplemented('Analysis timeline retrieval is scaffolded but not implemented yet.');
    }

    public function recommendations(AnalysisRun $analysisRun): JsonResponse
    {
        return $this->notImplemented('Analysis recommendations retrieval is scaffolded but not implemented yet.');
    }
}
