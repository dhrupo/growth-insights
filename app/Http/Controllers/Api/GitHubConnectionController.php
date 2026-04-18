<?php

namespace App\Http\Controllers\Api;

use App\Models\GitHubConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubConnectionController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        return $this->notImplemented('GitHub connection creation is scaffolded but not implemented yet.');
    }

    public function sync(GitHubConnection $githubConnection): JsonResponse
    {
        return $this->notImplemented('GitHub sync is scaffolded but not implemented yet.');
    }
}
