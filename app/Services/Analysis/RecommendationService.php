<?php

namespace App\Services\Analysis;

use LogicException;

class RecommendationService
{
    public function build(array $facts, array $metrics = [], array $signals = []): array
    {
        throw new LogicException('Recommendation generation is not implemented yet.');
    }
}
