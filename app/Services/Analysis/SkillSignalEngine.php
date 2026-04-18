<?php

namespace App\Services\Analysis;

class SkillSignalEngine
{
    public function __construct(private readonly SkillSignalBuilder $builder)
    {
    }

    public function build(array $facts): array
    {
        return $this->builder->build($facts);
    }
}
