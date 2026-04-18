<?php

namespace App\Services\GitHub;

use Illuminate\Http\Client\Factory;
use LogicException;

class GitHubClient
{
    public function __construct(private readonly Factory $http)
    {
    }

    public function fetchProfile(string $githubUsername): array
    {
        throw new LogicException('GitHub profile fetching is not implemented yet.');
    }

    public function fetchActivity(string $githubUsername): array
    {
        throw new LogicException('GitHub activity fetching is not implemented yet.');
    }
}
