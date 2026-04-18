<?php

namespace App\Services\Analysis;

class WeeklyBucketBuilder
{
    public function __construct(private readonly WeeklyTimelineBuilder $timelineBuilder)
    {
    }

    public function build(array $facts): array
    {
        return $this->timelineBuilder->build($facts);
    }
}
