<?php

namespace App\Services\Analysis;

class MetricEngine
{
    public function __construct(private readonly GrowthScoreCalculator $calculator)
    {
    }

    public function build(array $facts): array
    {
        return $this->calculator->calculate($facts);
    }
}
