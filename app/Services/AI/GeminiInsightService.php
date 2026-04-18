<?php

namespace App\Services\AI;

class GeminiInsightService
{
    public function enabled(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    public function enhance(array $analysisFacts): array
    {
        return [];
    }
}
