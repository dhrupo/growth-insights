<?php

namespace Tests\Feature\Api;

use App\Models\AnalysisRun;
use App\Models\GitHubConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class AnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);
    }

    public function test_public_analysis_creates_a_persisted_run_with_weekly_plan_and_signals(): void
    {
        Http::fake($this->analysisHttpFake($this->publicGitHubFake(), $this->geminiFakeResponse()));

        $response = $this->postJson('/api/analysis/public', [
            'github_username' => 'octocat',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.github_username', 'octocat')
            ->assertJsonPath('data.analysis_mode', 'public_only')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'github_username',
                    'analysis_mode',
                    'overall_score',
                    'confidence',
                    'momentum_label',
                    'strengths',
                    'weaknesses',
                    'weekly_plan',
                    'metric_snapshot',
                    'weekly_buckets',
                    'skill_signals',
                    'recommendations',
                    'ai_enhancement',
                ],
            ]);

        $analysisRun = AnalysisRun::query()->where('github_username', 'octocat')->firstOrFail();

        $this->assertSame('completed', $analysisRun->status);
        $this->assertSame('public_only', $analysisRun->analysis_mode);
        $this->assertNotNull($analysisRun->metricSnapshot);
        $this->assertGreaterThanOrEqual(7, $analysisRun->weeklyBuckets->count());
        $this->assertCount(7, $analysisRun->weekly_plan);
        $this->assertGreaterThan(0, $analysisRun->skillSignals->count());
        $this->assertGreaterThan(0, $analysisRun->recommendations->count());
        $this->assertNotEmpty($analysisRun->strengths);
        $this->assertNotEmpty($analysisRun->weaknesses);
        $this->assertSame('enhanced', $analysisRun->ai_status);
        $this->assertSame('gemini-2.5-flash', $analysisRun->ai_model);
        $this->assertFalse($analysisRun->ai_enhancement['cached']);
        $this->assertNotEmpty($analysisRun->ai_enhancement['merged_weekly_plan'][0]['ai_note'] ?? null);
        $this->assertNotEmpty($analysisRun->ai_enhancement['merged_recommendations'][0]['ai_note'] ?? null);
        $this->assertNotEmpty($analysisRun->context['repositories'] ?? []);
        $this->assertNotEmpty($analysisRun->context['trajectory'] ?? []);
        $this->assertNotEmpty($analysisRun->context['contribution_style']['label'] ?? null);
        $this->assertArrayHasKey('how_to_get_noticed', $analysisRun->ai_enhancement);
        $this->assertArrayHasKey('trajectory_12_months', $analysisRun->ai_enhancement);
    }

    public function test_token_backed_connection_sync_uses_authenticated_requests_and_supports_latest_lookup(): void
    {
        $connection = GitHubConnection::create([
            'user_id' => User::factory()->create()->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'access_token' => 'ghp_test_private_token_value',
            'sync_status' => 'idle',
            'connected_at' => now()->subDay(),
        ]);

        Http::fake($this->analysisHttpFake($this->privateGitHubFake(), $this->geminiFakeResponse()));

        $session = [
            'github.current_connection_id' => $connection->id,
            'github.current_username' => 'octocat',
        ];

        $syncResponse = $this->withSession($session)->postJson("/api/github/connections/{$connection->id}/sync");

        $syncResponse
            ->assertOk()
            ->assertJsonPath('data.github_connection_id', $connection->id)
            ->assertJsonPath('data.analysis_mode', 'public_private');

        $connection->refresh();

        $this->assertSame('complete', $connection->sync_status);
        $this->assertNotNull($connection->last_synced_at);

        $latestByConnection = $this->withSession($session)->getJson("/api/github/connections/{$connection->id}/analysis/latest");
        $latestByConnection
            ->assertOk()
            ->assertJsonPath('data.github_connection_id', $connection->id);

        $latestByUsername = $this->withSession($session)->getJson('/api/analysis/latest/by-username/octocat');
        $latestByUsername
            ->assertOk()
            ->assertJsonPath('data.github_username', 'octocat');
    }

    public function test_simulation_projects_score_changes_from_component_inputs(): void
    {
        $response = $this->postJson('/api/analysis/simulations', [
            'current_score' => 64,
            'current_consistency_score' => 60,
            'current_diversity_score' => 52,
            'current_contribution_score' => 58,
            'extra_prs_per_week' => 2,
            'extra_commits_per_week' => 4,
            'extra_repos' => 1,
            'extra_languages' => 1,
            'extra_testing_signals' => 1,
            'extra_collaboration_signals' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.current_score', 64)
            ->assertJsonPath('data.affected_dimensions.0', 'consistency');

        $this->assertGreaterThan(64, (float) $response->json('data.projected_score'));
    }

    public function test_dashboard_endpoints_reshape_latest_analysis_for_frontend_consumption(): void
    {
        Http::fake($this->analysisHttpFake($this->publicGitHubFake(), $this->geminiFakeResponse()));
        $runId = $this->postJson('/api/analysis/public', ['github_username' => 'octocat'])
            ->assertCreated()
            ->json('data.id');

        $query = http_build_query(['analysis_run_id' => $runId]);

        $this->getJson('/api/dashboard/summary?'.$query)
            ->assertOk()
            ->assertJsonCount(4, 'data.metrics');

        $this->getJson('/api/dashboard/timeline?'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'series',
                ],
            ]);

        $this->getJson('/api/dashboard/insights?'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'acquisitionMix',
                    'strengths',
                    'weaknesses',
                    'recommendations',
                ],
            ]);

        $this->getJson('/api/dashboard/simulator?'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'current',
                    'projected',
                    'uplift',
                    'series',
                ],
            ]);
    }

    public function test_dashboard_current_analysis_returns_empty_state_without_connection(): void
    {
        $this->getJson('/api/dashboard/github/current-analysis')
            ->assertOk()
            ->assertJsonPath('data.analysisRunId', null)
            ->assertJsonPath('data.connection.connected', false)
            ->assertJsonPath('data.profile.username', '')
            ->assertJsonPath('data.snapshot.score', null);
    }

    public function test_connection_routes_require_the_current_session_connection(): void
    {
        $ownerConnection = GitHubConnection::create([
            'user_id' => User::factory()->create()->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'access_token' => 'ghp_test_private_token_value',
            'sync_status' => 'idle',
            'connected_at' => now()->subDay(),
        ]);

        $otherConnection = GitHubConnection::create([
            'user_id' => User::factory()->create()->id,
            'github_username' => 'monalisa',
            'analysis_mode' => 'public_private',
            'access_token' => 'ghp_other_private_token_value',
            'sync_status' => 'idle',
            'connected_at' => now()->subHours(12),
        ]);

        Http::fake($this->analysisHttpFake($this->privateGitHubFake(), $this->geminiFakeResponse()));

        $this->postJson("/api/github/connections/{$ownerConnection->id}/sync")
            ->assertNotFound();

        $this->withSession([
            'github.current_connection_id' => $otherConnection->id,
            'github.current_username' => 'monalisa',
        ])->postJson("/api/github/connections/{$ownerConnection->id}/sync")
            ->assertNotFound();
    }

    public function test_private_analysis_routes_are_not_publicly_accessible(): void
    {
        $connection = GitHubConnection::create([
            'user_id' => null,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'access_token' => 'ghp_test_private_token_value',
            'sync_status' => 'complete',
            'connected_at' => now()->subDay(),
            'last_synced_at' => now()->subHour(),
        ]);

        $privateRun = AnalysisRun::create([
            'github_connection_id' => $connection->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'status' => 'completed',
            'source_window_start' => now()->subDays(55)->toDateString(),
            'source_window_end' => now()->toDateString(),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinute(),
            'overall_score' => 82.5,
            'confidence' => 88,
            'momentum_label' => 'growing',
            'weekly_plan' => [],
            'context' => [
                'repositories' => [
                    [
                        'full_name' => 'octocat/private-engine',
                        'visibility' => 'private',
                    ],
                ],
            ],
            'summary' => 'Private analysis run.',
            'evidence_summary' => 'Private evidence.',
        ]);

        $this->getJson('/api/analysis/latest/by-username/octocat')
            ->assertNotFound();

        $this->getJson("/api/analysis/{$privateRun->id}")
            ->assertNotFound();

        $this->getJson("/api/analysis/{$privateRun->id}/timeline")
            ->assertNotFound();

        $this->getJson("/api/analysis/{$privateRun->id}/recommendations")
            ->assertNotFound();

        $this->getJson('/api/dashboard/github/latest-analysis/octocat')
            ->assertNotFound();
    }

    public function test_current_analysis_does_not_fall_back_to_another_users_connection(): void
    {
        $connection = GitHubConnection::create([
            'user_id' => null,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'access_token' => 'ghp_test_private_token_value',
            'sync_status' => 'complete',
            'connected_at' => now()->subDay(),
            'last_synced_at' => now()->subHour(),
        ]);

        AnalysisRun::create([
            'github_connection_id' => $connection->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'status' => 'completed',
            'source_window_start' => now()->subDays(55)->toDateString(),
            'source_window_end' => now()->toDateString(),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinute(),
            'overall_score' => 82.5,
            'confidence' => 88,
            'momentum_label' => 'growing',
            'weekly_plan' => [],
            'context' => [],
            'summary' => 'Private analysis run.',
            'evidence_summary' => 'Private evidence.',
        ]);

        $this->getJson('/api/dashboard/github/current-analysis')
            ->assertOk()
            ->assertJsonPath('data.analysisRunId', null)
            ->assertJsonPath('data.connection.connected', false)
            ->assertJsonPath('data.profile.username', '');
    }

    public function test_dashboard_workbench_endpoints_return_analysis_payload_for_connected_flow(): void
    {
        $connection = GitHubConnection::create([
            'user_id' => null,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
            'access_token' => 'ghp_test_private_token_value',
            'sync_status' => 'idle',
            'connected_at' => now(),
        ]);

        $this->withSession([
            'github.current_connection_id' => $connection->id,
            'github.current_username' => 'octocat',
        ])->getJson('/api/dashboard/github/current-analysis')
            ->assertOk()
            ->assertJsonPath('data.analysisRunId', null)
            ->assertJsonPath('data.connection.connected', true)
            ->assertJsonPath('data.profile.username', 'octocat');

        Http::fake($this->analysisHttpFake($this->privateGitHubFake(), $this->geminiFakeResponse()));

        $this->withSession([
            'github.current_connection_id' => $connection->id,
            'github.current_username' => 'octocat',
        ])->postJson('/api/dashboard/github/current-analysis/sync')
            ->assertOk()
            ->assertJsonPath('data.connection.connected', true)
            ->assertJsonPath('data.source', 'mixed')
            ->assertJsonStructure([
                'data' => [
                    'analysisRunId',
                    'snapshot' => ['score', 'confidence', 'momentum', 'languages', 'activeWeeks', 'activeDays', 'commits', 'pullRequests', 'issues'],
                    'thirtyDayPlan',
                    'skillSignals',
                    'improvementActions',
                    'howToGetNoticed' => ['summary', 'actions'],
                    'trajectory' => ['windows', 'summary', 'outlook', 'confidence'],
                    'contributionStyle' => ['label', 'summary', 'confidence', 'evidence'],
                    'credibilityNotice',
                    'analyzedRepositories',
                    'suggestedRepositories',
                ],
            ]);

        $this->withSession([
            'github.current_connection_id' => $connection->id,
            'github.current_username' => 'octocat',
        ])->getJson('/api/dashboard/github/current-analysis')
            ->assertOk()
            ->assertJsonPath('data.profile.username', 'octocat')
            ->assertJsonPath('data.connection.connected', true)
            ->assertJsonPath('data.source', 'mixed');

        $payload = $this->withSession([
            'github.current_connection_id' => $connection->id,
            'github.current_username' => 'octocat',
        ])->getJson('/api/dashboard/github/current-analysis')->json('data');

        $this->assertNotEmpty($payload['contributionStyle']['label'] ?? null);
        $this->assertNotEmpty($payload['analyzedRepositories'] ?? []);
    }

    private function geminiFakeResponse(): array
    {
        return [
            'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'summary' => 'The profile is stable, with AI emphasis on consistency and one clearer next step.',
                                        'weekly_plan_notes' => [
                                            [
                                                'index' => 0,
                                                'ai_note' => 'Keep this block focused on a single visible contribution.',
                                            ],
                                            [
                                                'index' => 3,
                                                'ai_note' => 'Use the collaboration step to create a reviewable artifact.',
                                            ],
                                        ],
                                        'recommendation_notes' => [
                                            [
                                                'index' => 0,
                                                'ai_note' => 'This should preserve cadence without increasing scope too much.',
                                                'confidence' => 'high',
                                            ],
                                            [
                                                'index' => 1,
                                                'ai_note' => 'The weakest signal should move fastest with a concrete test or breadth change.',
                                                'confidence' => 'medium',
                                            ],
                                        ],
                                        'thirty_day_plan_notes' => [
                                            [
                                                'index' => 0,
                                                'ai_note' => 'Keep the first week scoped to one visible repository.',
                                            ],
                                        ],
                                        'improvement_actions' => [
                                            [
                                                'title' => 'Raise public reviewability',
                                                'detail' => 'Open one smaller pull request weekly with a validation note.',
                                                'why' => 'This increases visible collaboration signal fastest.',
                                                'metric' => '1 public PR per week',
                                            ],
                                        ],
                                        'how_to_get_noticed' => [
                                            'summary' => 'More reviewable public work will make this profile easier to notice.',
                                            'actions' => [
                                                [
                                                    'action' => 'Write clearer PR titles and descriptions.',
                                                    'why' => 'Better framing helps others evaluate contributions faster.',
                                                    'evidence' => 'Current visibility is more commit-heavy than PR-heavy.',
                                                ],
                                            ],
                                        ],
                                        'trajectory_12_months' => [
                                            'summary' => 'If the current pace continues, the visible profile should strengthen as a reliable collaborator.',
                                            'outlook' => 'The strongest compounding path is steady visible output plus more reviewable pull requests.',
                                            'confidence' => 'medium',
                                        ],
                                        'suggested_repositories' => [
                                            [
                                                'repo' => 'laravel/framework',
                                                'why_fit' => 'Matches the visible PHP and backend part of the profile.',
                                                'realistic_contribution' => 'Start with tests, docs, or a small bug fix.',
                                            ],
                                        ],
                                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ];
    }

    private function analysisHttpFake(array $githubFixtures, array $geminiFixtures): array
    {
        return array_merge($githubFixtures, $geminiFixtures);
    }

    private function publicGitHubFake(): array
    {
        return [
            'https://api.github.com/users/octocat' => Http::response([
                'login' => 'octocat',
                'name' => 'Octo Cat',
                'public_repos' => 2,
                'followers' => 120,
                'following' => 11,
                'html_url' => 'https://github.com/octocat',
            ]),
            'https://api.github.com/users/octocat/repos*' => Http::response([
                [
                    'full_name' => 'octocat/project-alpha',
                    'name' => 'project-alpha',
                    'archived' => false,
                    'updated_at' => now()->subDay()->toIso8601String(),
                ],
                [
                    'full_name' => 'octocat/ui-labs',
                    'name' => 'ui-labs',
                    'archived' => false,
                    'updated_at' => now()->subDays(2)->toIso8601String(),
                ],
            ]),
            'https://api.github.com/repos/octocat/project-alpha/languages' => Http::response([
                'PHP' => 90000,
                'JavaScript' => 30000,
                'Blade' => 12000,
            ]),
            'https://api.github.com/repos/octocat/ui-labs/languages' => Http::response([
                'JavaScript' => 110000,
                'Vue' => 32000,
                'CSS' => 10000,
            ]),
            'https://api.github.com/repos/octocat/project-alpha/commits*' => Http::response([
                [
                    'commit' => [
                        'author' => ['date' => now()->subDays(6)->toIso8601String()],
                        'message' => 'Refactor API scoring pipeline',
                    ],
                ],
                [
                    'commit' => [
                        'author' => ['date' => now()->subDays(13)->toIso8601String()],
                        'message' => 'Add analysis persistence tests',
                    ],
                ],
            ]),
            'https://api.github.com/repos/octocat/ui-labs/commits*' => Http::response([
                [
                    'commit' => [
                        'author' => ['date' => now()->subDays(3)->toIso8601String()],
                        'message' => 'Improve dashboard layout',
                    ],
                ],
            ]),
            'https://api.github.com/search/issues*' => function ($request) {
                $query = urldecode((string) parse_url($request->url(), PHP_URL_QUERY));

                return match (true) {
                    str_contains($query, 'type:pr') && str_contains($query, 'project-alpha') => Http::response([
                        'items' => [
                            [
                                'title' => 'Add API caching layer',
                                'created_at' => now()->subDays(9)->toIso8601String(),
                                'pull_request' => ['url' => 'https://api.github.com/repos/octocat/project-alpha/pulls/1'],
                            ],
                        ],
                    ]),
                    str_contains($query, 'type:pr') && str_contains($query, 'ui-labs') => Http::response([
                        'items' => [
                            [
                                'title' => 'Polish dashboard cards',
                                'created_at' => now()->subDays(5)->toIso8601String(),
                                'pull_request' => ['url' => 'https://api.github.com/repos/octocat/ui-labs/pulls/2'],
                            ],
                        ],
                    ]),
                    str_contains($query, 'type:issue') && str_contains($query, 'project-alpha') => Http::response([
                        'items' => [
                            [
                                'title' => 'Document analysis outputs',
                                'created_at' => now()->subDays(4)->toIso8601String(),
                            ],
                        ],
                    ]),
                    str_contains($query, 'type:issue') && str_contains($query, 'ui-labs') => Http::response([
                        'items' => [
                            [
                                'title' => 'Track chart edge cases',
                                'created_at' => now()->subDays(2)->toIso8601String(),
                            ],
                        ],
                    ]),
                    default => Http::response(['items' => []]),
                };
            },
        ];
    }

    private function privateGitHubFake(): array
    {
        return [
            'https://api.github.com/user' => function ($request) {
                return Http::response([
                    'login' => 'octocat',
                    'name' => 'Octo Cat',
                    'public_repos' => 3,
                    'followers' => 150,
                    'following' => 14,
                    'html_url' => 'https://github.com/octocat',
                ]);
            },
            'https://api.github.com/user/repos*' => function ($request) {
                $authorization = $request->header('Authorization')[0] ?? null;
                Assert::assertSame('Bearer ghp_test_private_token_value', $authorization);

                return Http::response([
                    [
                        'full_name' => 'octocat/project-alpha',
                        'name' => 'project-alpha',
                        'archived' => false,
                        'updated_at' => now()->subDay()->toIso8601String(),
                    ],
                    [
                        'full_name' => 'octocat/private-engine',
                        'name' => 'private-engine',
                        'archived' => false,
                        'updated_at' => now()->subHours(10)->toIso8601String(),
                    ],
                ]);
            },
            'https://api.github.com/repos/octocat/project-alpha/languages' => Http::response([
                'PHP' => 100000,
                'JavaScript' => 22000,
            ]),
            'https://api.github.com/repos/octocat/private-engine/languages' => Http::response([
                'PHP' => 82000,
                'Dockerfile' => 1000,
            ]),
            'https://api.github.com/repos/octocat/project-alpha/commits*' => Http::response([
                [
                    'commit' => [
                        'author' => ['date' => now()->subDays(7)->toIso8601String()],
                        'message' => 'Add private repo metric hooks',
                    ],
                ],
                [
                    'commit' => [
                        'author' => ['date' => now()->subDays(12)->toIso8601String()],
                        'message' => 'Improve test coverage',
                    ],
                ],
            ]),
            'https://api.github.com/repos/octocat/private-engine/commits*' => Http::response([
                [
                    'commit' => [
                        'author' => ['date' => now()->subDays(1)->toIso8601String()],
                        'message' => 'Ship private engine sync endpoint',
                    ],
                ],
            ]),
            'https://api.github.com/search/issues*' => function ($request) {
                $query = urldecode((string) parse_url($request->url(), PHP_URL_QUERY));

                return match (true) {
                    str_contains($query, 'type:pr') && str_contains($query, 'project-alpha') => Http::response([
                        'items' => [
                            [
                                'title' => 'Add token-backed sync',
                                'created_at' => now()->subDays(8)->toIso8601String(),
                                'pull_request' => ['url' => 'https://api.github.com/repos/octocat/project-alpha/pulls/12'],
                            ],
                        ],
                    ]),
                    str_contains($query, 'type:pr') && str_contains($query, 'private-engine') => Http::response([
                        'items' => [
                            [
                                'title' => 'Refine private sync flow',
                                'created_at' => now()->subDays(2)->toIso8601String(),
                                'pull_request' => ['url' => 'https://api.github.com/repos/octocat/private-engine/pulls/13'],
                            ],
                        ],
                    ]),
                    str_contains($query, 'type:issue') && str_contains($query, 'project-alpha') => Http::response([
                        'items' => [
                            [
                                'title' => 'Document token usage',
                                'created_at' => now()->subDays(4)->toIso8601String(),
                            ],
                        ],
                    ]),
                    str_contains($query, 'type:issue') && str_contains($query, 'private-engine') => Http::response([
                        'items' => [
                            [
                                'title' => 'Track sync error states',
                                'created_at' => now()->subDays(3)->toIso8601String(),
                            ],
                        ],
                    ]),
                    default => Http::response(['items' => []]),
                };
            },
        ];
    }
}
