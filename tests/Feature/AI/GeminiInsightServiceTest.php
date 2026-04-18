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
        ];
    }
}
