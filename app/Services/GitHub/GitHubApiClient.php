<?php

namespace App\Services\GitHub;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

class GitHubApiClient
{
    public function __construct(private readonly Factory $http)
    {
    }

    public function publicProfile(string $githubUsername): array
    {
        return $this->request('get', "/users/{$githubUsername}");
    }

    public function authenticatedProfile(string $token): array
    {
        return $this->request('get', '/user', [], $token);
    }

    public function publicRepositories(string $githubUsername): array
    {
        return $this->paginate(
            "/users/{$githubUsername}/repos",
            ['sort' => 'updated', 'direction' => 'desc', 'type' => 'owner'],
            null,
        );
    }

    public function authenticatedRepositories(string $token): array
    {
        return $this->paginate(
            '/user/repos',
            ['sort' => 'updated', 'direction' => 'desc', 'visibility' => 'all', 'affiliation' => 'owner,collaborator'],
            $token,
        );
    }

    public function repositoryLanguages(string $repositoryFullName, ?string $token = null): array
    {
        return $this->request('get', "/repos/{$repositoryFullName}/languages", [], $token);
    }

    public function repositoryCommits(string $repositoryFullName, string $githubUsername, CarbonInterface $since, ?string $token = null): array
    {
        return $this->paginate(
            "/repos/{$repositoryFullName}/commits",
            [
                'author' => $githubUsername,
                'since' => $since->toIso8601String(),
            ],
            $token,
        );
    }

    public function repositoryPullRequests(string $repositoryFullName, string $githubUsername, CarbonInterface $since, ?string $token = null): array
    {
        $items = $this->searchIssues(
            sprintf('repo:%s type:pr author:%s created:>=%s', $repositoryFullName, $githubUsername, $since->toDateString()),
            $token,
        );

        return array_values(array_filter($items, static fn (array $item): bool => (bool) ($item['pull_request'] ?? false)));
    }

    public function repositoryIssues(string $repositoryFullName, string $githubUsername, CarbonInterface $since, ?string $token = null): array
    {
        return $this->searchIssues(
            sprintf('repo:%s type:issue author:%s created:>=%s', $repositoryFullName, $githubUsername, $since->toDateString()),
            $token,
        );
    }

    public function searchIssues(string $query, ?string $token = null): array
    {
        $response = $this->pending($token)->get('/search/issues', [
            'q' => $query,
            'per_page' => 100,
            'page' => 1,
        ]);

        return $response->throw()->json('items', []);
    }

    private function request(string $method, string $path, array $query = [], ?string $token = null): array
    {
        $response = $this->pending($token)->{$method}($path, $query);

        return $response->throw()->json();
    }

    private function paginate(string $path, array $query = [], ?string $token = null, int $maxPages = 3): array
    {
        $results = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = $this->pending($token)->get($path, $query + [
                'per_page' => 100,
                'page' => $page,
            ]);

            $items = $response->throw()->json();

            if (! is_array($items) || $items === []) {
                break;
            }

            $results = array_merge($results, $items);

            if (count($items) < 100) {
                break;
            }
        }

        return $results;
    }

    private function pending(?string $token = null): PendingRequest
    {
        $request = $this->http
            ->baseUrl(config('services.github.base_uri'))
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'GrowthInsights/1.0',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);

        if ($token !== null && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
