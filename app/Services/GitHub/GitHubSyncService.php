<?php

namespace App\Services\GitHub;

use App\Models\AnalysisRun;
use App\Models\GitHubConnection;
use App\Services\Analysis\MetricEngine;
use App\Services\Analysis\RecommendationService;
use App\Services\Analysis\SkillSignalEngine;
use App\Services\Analysis\WeeklyBucketBuilder;
use LogicException;

class GitHubSyncService
{
    public function __construct(
        private readonly GitHubClient $client,
        private readonly MetricEngine $metricEngine,
        private readonly WeeklyBucketBuilder $weeklyBucketBuilder,
        private readonly SkillSignalEngine $skillSignalEngine,
        private readonly RecommendationService $recommendationService,
    ) {
    }

    public function sync(GitHubConnection $connection): AnalysisRun
    {
        throw new LogicException('GitHub sync orchestration is not implemented yet.');
    }
}
