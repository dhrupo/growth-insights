<?php

namespace App\Services\Analysis;

class RecommendationService
{
    public function __construct(private readonly RecommendationBuilder $builder)
    {
    }

    public function build(array $facts, array $metrics = [], array $signals = []): array
    {
        $momentumLabel = (string) ($facts['momentum_label'] ?? 'stable');

        return $this->builder->build($facts, $metrics, $signals, $momentumLabel);
    }
}
