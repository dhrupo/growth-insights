<?php

namespace Tests\Feature\AI;

use App\Services\AI\GeminiInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiInsightServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.fallback_model' => 'gemini-2.0-flash',
            'services.gemini.cache_ttl_minutes' => 60,
            'services.gemini.timeout_seconds' => 10,
        ]);
    }

    public function test_it_caches_an_enhancement_for_the_same_snapshot_and_avoids_duplicate_http_calls(): void
    {
        config(['services.gemini.api_key' => 'test-gemini-key']);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'summary' => 'AI summary for the snapshot.',
                                        'weekly_plan_notes' => [
                                            ['index' => 0, 'ai_note' => 'Stay narrow and ship the first item.'],
                                        ],
                                        'recommendation_notes' => [
                                            ['index' => 1, 'ai_note' => 'This is the fastest signal to lift.', 'confidence' => 'high'],
                                        ],
                                        'thirty_day_plan_notes' => [
                                            ['index' => 0, 'ai_note' => 'Keep the first week narrowly scoped.'],
                                        ],
                                        'improvement_actions' => [
                                            ['title' => 'Raise PR visibility', 'detail' => 'Open one smaller PR weekly.', 'why' => 'Reviewable work gets noticed faster.', 'metric' => '1 PR/week'],
                                        ],
                                        'how_to_get_noticed' => [
                                            'summary' => 'More reviewable public artifacts will increase visibility.',
                                            'actions' => [
                                                ['action' => 'Write clearer PR descriptions', 'why' => 'They are easier to evaluate quickly.', 'evidence' => 'Low visible PR volume.'],
                                            ],
                                        ],
                                        'trajectory_12_months' => [
                                            'summary' => 'This profile is trending toward a stronger collaborator signal.',
                                            'outlook' => 'If current pace holds, the visible profile should become easier to trust and review.',
                                            'confidence' => 'medium',
                                        ],
                                        'suggested_repositories' => [
                                            ['repo' => 'laravel/framework', 'why_fit' => 'Matches backend-heavy visible work.', 'realistic_contribution' => 'Start with docs or test fixes.'],
                                        ],
                                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(GeminiInsightService::class);

        $facts = $this->analysisFacts();
        $first = $service->enhance($facts);
        $second = $service->enhance($facts);

        $this->assertSame('enhanced', $first['status']);
        $this->assertFalse($first['cached']);
        $this->assertTrue($second['cached']);
        $this->assertSame($first['snapshot_hash'], $second['snapshot_hash']);
        $this->assertSame($first['summary'], $second['summary']);
        $this->assertCount(1, $first['improvement_actions']);
        $this->assertSame('More reviewable public artifacts will increase visibility.', $first['how_to_get_noticed']['summary']);
        $this->assertSame('This profile is trending toward a stronger collaborator signal.', $first['trajectory_12_months']['summary']);
        $this->assertCount(1, $first['thirty_day_plan_notes']);

        $geminiRequests = collect(Http::recorded())->filter(function (array $record): bool {
            return str_contains($record[0]->url(), 'generativelanguage.googleapis.com');
        });

        $this->assertCount(1, $geminiRequests);
    }

    public function test_it_gracefully_falls_back_without_a_gemini_key(): void
    {
        config(['services.gemini.api_key' => null]);

        Http::fake();

        $service = app(GeminiInsightService::class);
        $result = $service->enhance($this->analysisFacts());

        $this->assertSame('failed', $result['status']);
        $this->assertSame('missing_api_key', $result['error']);
        $this->assertSame([], $result['weekly_plan_notes']);
        $this->assertSame([], $result['recommendation_notes']);
        $this->assertSame([], $result['improvement_actions']);
        $this->assertSame('', $result['how_to_get_noticed']['summary']);

        Http::assertNothingSent();
    }

    public function test_it_falls_back_to_the_secondary_model_when_the_primary_model_is_overloaded(): void
    {
        config(['services.gemini.api_key' => 'test-gemini-key']);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'error' => [
                    'code' => 503,
                    'message' => 'Model overloaded',
                ],
            ], 503),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'summary' => 'Fallback model succeeded.',
                                        'weekly_plan_notes' => [],
                                        'recommendation_notes' => [],
                                        'thirty_day_plan_notes' => [],
                                        'improvement_actions' => [],
                                        'how_to_get_noticed' => ['summary' => '', 'actions' => []],
                                        'trajectory_12_months' => ['summary' => '', 'outlook' => '', 'confidence' => 'low'],
                                        'suggested_repositories' => [],
                                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(GeminiInsightService::class);
        $result = $service->enhance($this->analysisFacts());

        $this->assertSame('enhanced', $result['status']);
        $this->assertSame('gemini-2.0-flash', $result['model']);
        $this->assertSame('Fallback model succeeded.', $result['summary']);
    }

    public function test_it_falls_back_when_the_cached_snapshot_payload_is_corrupted(): void
    {
        config(['services.gemini.api_key' => 'test-gemini-key']);

        $service = app(GeminiInsightService::class);
        $facts = $this->analysisFacts();
        $snapshotHash = $service->snapshotHash($facts);

        Cache::put("gemini:analysis:{$snapshotHash}", 'corrupted-cache-value', now()->addMinutes(10));

        $result = $service->enhance($facts);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('cache_corrupted', $result['error']);
    }

    public function test_it_falls_back_when_gemini_returns_malformed_json(): void
    {
        config(['services.gemini.api_key' => 'test-gemini-key']);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'not-json',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(GeminiInsightService::class);
        $result = $service->enhance($this->analysisFacts());

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Gemini response was not valid JSON.', $result['error']);
    }

    private function analysisFacts(): array
    {
        return [
            'profile' => [
                'login' => 'octocat',
                'analysis_mode' => 'public_only',
            ],
            'metrics' => [
                'overall_score' => 74.2,
                'confidence' => 81.4,
            ],
            'strengths' => [
                [
                    'key' => 'consistency',
                    'title' => 'Strong contribution cadence',
                    'score' => 81,
                    'evidence' => '4 active weeks and 16 active days.',
                ],
            ],
            'weaknesses' => [
                [
                    'key' => 'testing',
                    'title' => 'Testing signal is weak',
                    'score' => 42,
                    'evidence' => 'Few test-related signals.',
                ],
            ],
            'weekly_plan' => [
                ['day' => 'Day 1', 'title' => 'Pick one repo', 'action' => 'Choose a target.'],
                ['day' => 'Day 2', 'title' => 'Ship a change', 'action' => 'Make one change.'],
            ],
            'thirty_day_plan' => [
                ['week' => 'Week 1', 'title' => 'Stabilize your baseline', 'action' => 'Ship one visible improvement.'],
            ],
            'recommendations' => [
                ['title' => 'Stay consistent', 'body' => 'Keep a weekly cadence.', 'sort_order' => 1],
                ['title' => 'Improve testing', 'body' => 'Add one test.', 'sort_order' => 2],
            ],
            'skill_signals' => [
                ['skill_key' => 'backend', 'score' => 85, 'confidence' => 88, 'evidence' => []],
            ],
            'weekly_buckets' => [
                ['week_start' => '2026-04-01', 'week_end' => '2026-04-07', 'total_events' => 6],
            ],
            'evidence_summary' => '4 active weeks, 16 active days, 12 commits, 3 PRs, and 1 issue.',
            'repo_summary' => ['sampled_count' => 4, 'repos_touched' => 2, 'top_languages' => ['php', 'javascript']],
            'trend_windows' => ['last_month' => ['score' => 74.2, 'confidence' => 81.4]],
            'contribution_style' => ['label' => 'Builder', 'summary' => 'Build-and-ship profile.'],
            'visibility_advice' => ['summary' => 'More reviewable work increases visibility.', 'actions' => []],
            'suggested_repositories' => [['repo' => 'laravel/framework', 'why_fit' => 'Backend fit.', 'realistic_contribution' => 'Docs or tests.']],
        ];
    }
}
