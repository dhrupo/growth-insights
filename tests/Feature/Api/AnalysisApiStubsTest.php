<?php

namespace Tests\Feature\Api;

use App\Models\AnalysisRun;
use App\Models\GitHubConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisApiStubsTest extends TestCase
{
    use RefreshDatabase;

    public function test_github_connection_creation_is_stubbed(): void
    {
        $this->postJson('/api/github/connections')
            ->assertStatus(501)
            ->assertJson([
                'message' => 'GitHub connection creation is scaffolded but not implemented yet.',
            ]);
    }

    public function test_github_connection_sync_is_stubbed(): void
    {
        $connection = GitHubConnection::create([
            'user_id' => User::factory()->create()->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_only',
            'sync_status' => 'idle',
        ]);

        $this->postJson("/api/github/connections/{$connection->id}/sync")
            ->assertStatus(501)
            ->assertJson([
                'message' => 'GitHub sync is scaffolded but not implemented yet.',
            ]);
    }

    public function test_latest_analysis_route_is_stubbed(): void
    {
        $this->getJson('/api/analysis/latest')
            ->assertStatus(501)
            ->assertJson([
                'message' => 'Latest analysis lookup is scaffolded but not implemented yet.',
            ]);
    }

    public function test_analysis_detail_routes_are_stubbed(): void
    {
        $connection = GitHubConnection::create([
            'user_id' => User::factory()->create()->id,
            'github_username' => 'octocat',
            'analysis_mode' => 'public_only',
            'sync_status' => 'idle',
        ]);

        $analysisRun = AnalysisRun::create([
            'github_connection_id' => $connection->id,
            'status' => 'complete',
        ]);

        $this->getJson("/api/analysis/{$analysisRun->id}")
            ->assertStatus(501)
            ->assertJson([
                'message' => 'Analysis detail retrieval is scaffolded but not implemented yet.',
            ]);

        $this->getJson("/api/analysis/{$analysisRun->id}/timeline")
            ->assertStatus(501)
            ->assertJson([
                'message' => 'Analysis timeline retrieval is scaffolded but not implemented yet.',
            ]);

        $this->getJson("/api/analysis/{$analysisRun->id}/recommendations")
            ->assertStatus(501)
            ->assertJson([
                'message' => 'Analysis recommendations retrieval is scaffolded but not implemented yet.',
            ]);
    }
}
