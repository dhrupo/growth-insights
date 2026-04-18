<?php

namespace Tests\Feature\Domain;

use App\Http\Resources\AnalysisRunResource;
use App\Models\AnalysisMetricSnapshot;
use App\Models\AnalysisRecommendation;
use App\Models\AnalysisRun;
use App\Models\AnalysisWeeklyBucket;
use App\Models\GitHubConnection;
use App\Models\SkillSignal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisDomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_models_persist_and_relates_as_expected(): void
    {
        $user = User::factory()->create();

        $connection = GitHubConnection::create([
            'user_id' => $user->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'sync_status' => 'idle',
            'connected_at' => now(),
        ]);

        $analysisRun = AnalysisRun::create([
            'github_connection_id' => $connection->id,
            'status' => 'complete',
            'source_window_start' => now()->subDays(28)->toDateString(),
            'source_window_end' => now()->toDateString(),
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'overall_score' => 78.50,
            'confidence' => 0.84,
            'summary' => 'Solid backend growth with stable contribution cadence.',
        ]);

        $metricSnapshot = AnalysisMetricSnapshot::create([
            'analysis_run_id' => $analysisRun->id,
            'consistency_score' => 82.00,
            'diversity_score' => 71.00,
            'contribution_score' => 80.00,
            'collaboration_score' => 64.00,
            'testing_score' => 55.00,
            'documentation_score' => 61.00,
            'momentum_score' => 74.00,
            'score_breakdown' => [
                'consistency' => 32.8,
                'diversity' => 21.3,
                'contribution' => 24.0,
            ],
            'evidence' => [
                'commits' => 42,
                'pull_requests' => 6,
            ],
        ]);

        $weeklyBucket = AnalysisWeeklyBucket::create([
            'analysis_run_id' => $analysisRun->id,
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'active_days' => 4,
            'commits_count' => 12,
            'pull_requests_count' => 2,
            'issues_count' => 1,
            'reviews_count' => 3,
            'momentum_label' => 'stable',
            'trend_direction' => 'flat',
            'evidence' => [
                'week_label' => 'Week 1',
            ],
        ]);

        $skillSignal = SkillSignal::create([
            'analysis_run_id' => $analysisRun->id,
            'skill_key' => 'backend',
            'score' => 86.00,
            'confidence' => 0.90,
            'evidence' => [
                'composer_json' => true,
                'php_files' => 24,
            ],
        ]);

        $recommendation = AnalysisRecommendation::create([
            'analysis_run_id' => $analysisRun->id,
            'category' => 'suggestion',
            'title' => 'Strengthen test coverage',
            'body' => 'Add one focused test path around the highest-change backend feature.',
            'sort_order' => 1,
            'metadata' => [
                'rationale' => 'Testing signal is weaker than the rest of the profile.',
            ],
        ]);

        $analysisRun->load(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);

        $this->assertTrue($connection->user->is($user));
        $this->assertTrue($connection->analysisRuns->contains($analysisRun));
        $this->assertTrue($analysisRun->githubConnection->is($connection));
        $this->assertTrue($analysisRun->metricSnapshot->is($metricSnapshot));
        $this->assertTrue($analysisRun->weeklyBuckets->contains($weeklyBucket));
        $this->assertTrue($analysisRun->skillSignals->contains($skillSignal));
        $this->assertTrue($analysisRun->recommendations->contains($recommendation));
    }

    public function test_analysis_run_resource_exposes_expected_shape(): void
    {
        $connection = GitHubConnection::create([
            'user_id' => User::factory()->create()->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_only',
            'sync_status' => 'idle',
        ]);

        $analysisRun = AnalysisRun::create([
            'github_connection_id' => $connection->id,
            'status' => 'pending',
        ])->load(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);

        $payload = (new AnalysisRunResource($analysisRun))->resolve();

        $this->assertSame('pending', $payload['status']);
        $this->assertArrayHasKey('metric_snapshot', $payload);
        $this->assertArrayHasKey('weekly_buckets', $payload);
        $this->assertArrayHasKey('skill_signals', $payload);
        $this->assertArrayHasKey('recommendations', $payload);
    }
}
