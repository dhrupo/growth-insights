<?php

namespace Tests\Feature\Domain;

use App\Services\GitHub\GitHubApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class GitHubApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_collects_multiple_pages_of_commit_results(): void
    {
        Http::fake([
            'https://api.github.com/repos/octocat/project-alpha/commits*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return match ((int) ($query['page'] ?? 1)) {
                    1 => Http::response($this->commitPage(100)),
                    2 => Http::response($this->commitPage(5)),
                    default => Http::response([]),
                };
            },
        ]);

        $commits = app(GitHubApiClient::class)->repositoryCommits(
            'octocat/project-alpha',
            'octocat',
            now()->subYear(),
        );

        $this->assertCount(105, $commits);
    }

    public function test_it_collects_multiple_pages_of_search_results(): void
    {
        Http::fake([
            'https://api.github.com/search/issues*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return match ((int) ($query['page'] ?? 1)) {
                    1 => Http::response([
                        'items' => $this->pullRequestPage(100),
                    ]),
                    2 => Http::response([
                        'items' => $this->pullRequestPage(1),
                    ]),
                    default => Http::response(['items' => []]),
                };
            },
        ]);

        $pullRequests = app(GitHubApiClient::class)->authorPullRequests('octocat', now()->subYear());

        $this->assertCount(101, $pullRequests);
    }

    public function test_public_repositories_request_uses_all_visible_repositories(): void
    {
        Http::fake([
            'https://api.github.com/users/octocat/repos*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                Assert::assertSame('all', $query['type'] ?? null);

                return Http::response([
                    [
                        'full_name' => 'octocat/project-alpha',
                    ],
                ]);
            },
        ]);

        $repositories = app(GitHubApiClient::class)->publicRepositories('octocat');

        $this->assertCount(1, $repositories);
    }

    private function commitPage(int $count): array
    {
        return array_map(static fn (int $index): array => [
            'sha' => sprintf('sha-%03d', $index),
            'commit' => [
                'author' => ['date' => now()->subDays($index + 1)->toIso8601String()],
                'message' => "Commit {$index}",
            ],
        ], range(1, $count));
    }

    private function pullRequestPage(int $count): array
    {
        return array_map(static fn (int $index): array => [
            'id' => $index,
            'title' => "Pull request {$index}",
            'created_at' => now()->subDays($index + 1)->toIso8601String(),
            'pull_request' => [
                'url' => "https://api.github.com/repos/octocat/project-alpha/pulls/{$index}",
            ],
        ], range(1, $count));
    }
}
