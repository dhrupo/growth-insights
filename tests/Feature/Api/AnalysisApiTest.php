<?php

namespace Tests\Feature\Api;

use App\Models\AnalysisRun;
use App\Models\GitHubConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class AnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_analysis_creates_a_persisted_run_with_weekly_plan_and_signals(): void
    {
        Http::fake($this->publicGitHubFake());

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

        Http::fake($this->privateGitHubFake());

        $syncResponse = $this->postJson("/api/github/connections/{$connection->id}/sync");

        $syncResponse
            ->assertOk()
            ->assertJsonPath('data.github_connection_id', $connection->id)
            ->assertJsonPath('data.analysis_mode', 'public_private');

        $connection->refresh();

        $this->assertSame('complete', $connection->sync_status);
        $this->assertNotNull($connection->last_synced_at);

        $latestByConnection = $this->getJson("/api/github/connections/{$connection->id}/analysis/latest");
        $latestByConnection
            ->assertOk()
            ->assertJsonPath('data.github_connection_id', $connection->id);

        $latestByUsername = $this->getJson('/api/analysis/latest/by-username/octocat');
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
        Http::fake($this->publicGitHubFake());
        $this->postJson('/api/analysis/public', ['github_username' => 'octocat'])->assertCreated();

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonCount(4, 'data.metrics');

        $this->getJson('/api/dashboard/timeline')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'series',
                ],
            ]);

        $this->getJson('/api/dashboard/insights')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'acquisitionMix',
                    'strengths',
                    'weaknesses',
                    'recommendations',
                ],
            ]);

        $this->getJson('/api/dashboard/simulator')
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
