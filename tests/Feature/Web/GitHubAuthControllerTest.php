<?php

namespace Tests\Feature\Web;

use App\Models\GitHubConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_returns_home_when_oauth_config_is_incomplete(): void
    {
        config([
            'services.github.client_id' => null,
            'services.github.redirect_uri' => null,
        ]);

        $this->get('/auth/github/redirect')
            ->assertRedirect('/');
    }

    public function test_redirect_stores_a_state_value_and_redirects_to_github(): void
    {
        config([
            'services.github.client_id' => 'github-client-id',
            'services.github.redirect_uri' => 'http://localhost/auth/github/callback',
        ]);

        $response = $this->get('/auth/github/redirect');

        $response->assertRedirectContains('https://github.com/login/oauth/authorize');
        $this->assertNotNull(session('github.oauth.state'));
    }

    public function test_callback_rejects_a_state_mismatch(): void
    {
        config([
            'services.github.client_id' => 'github-client-id',
            'services.github.client_secret' => 'github-client-secret',
            'services.github.redirect_uri' => 'http://localhost/auth/github/callback',
        ]);

        $this->withSession([
            'github.oauth.state' => 'expected-state',
        ])->get('/auth/github/callback?code=test-code&state=wrong-state')
            ->assertRedirect('/');

        $this->assertDatabaseCount('github_connections', 0);
    }

    public function test_callback_stores_the_connected_github_account_in_session(): void
    {
        config([
            'services.github.client_id' => 'github-client-id',
            'services.github.client_secret' => 'github-client-secret',
            'services.github.redirect_uri' => 'http://localhost/auth/github/callback',
        ]);

        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'gho_test_token',
            ]),
            'https://api.github.com/user' => Http::response([
                'login' => 'octocat',
                'name' => 'Octo Cat',
            ]),
        ]);

        $response = $this->withSession([
            'github.oauth.state' => 'expected-state',
        ])->get('/auth/github/callback?code=test-code&state=expected-state');

        $response->assertRedirect('/');
        $this->assertDatabaseHas('github_connections', [
            'github_username' => 'octocat',
            'analysis_mode' => 'public_private',
        ]);

        $connection = GitHubConnection::query()->where('github_username', 'octocat')->firstOrFail();

        $response->assertSessionHas('github.current_connection_id', $connection->id);
        $response->assertSessionHas('github.current_username', 'octocat');
    }
}
