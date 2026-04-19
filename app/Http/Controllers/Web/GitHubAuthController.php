<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GitHubConnection;
use App\Services\GitHub\GitHubApiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GitHubAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.github.client_id');
        $redirectUri = (string) config('services.github.redirect_uri');

        if ($clientId === '' || $redirectUri === '') {
            Log::warning('GitHub OAuth redirect skipped because configuration is incomplete.', [
                'session' => $request->session()->getId(),
            ]);
            return redirect('/');
        }

        $state = Str::random(40);

        $request->session()->put('github.oauth.state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'repo read:user user:email',
            'state' => $state,
            'allow_signup' => 'false',
        ]);

        return redirect()->away("https://github.com/login/oauth/authorize?{$query}");
    }

    public function callback(
        Request $request,
        GitHubApiClient $gitHubApiClient,
    ): RedirectResponse {
        $state = (string) $request->session()->pull('github.oauth.state', '');

        if ($request->filled('error')) {
            Log::warning('GitHub OAuth callback returned an authorization error.', [
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
            ]);
            return redirect('/');
        }

        if ($state === '' || $request->query('state') !== $state) {
            Log::warning('GitHub OAuth callback failed state verification.', [
                'expected_state' => $state !== '' ? 'present' : 'missing',
                'received_state' => $request->filled('state') ? 'present' : 'missing',
            ]);
            return redirect('/');
        }

        $clientId = (string) config('services.github.client_id');
        $clientSecret = (string) config('services.github.client_secret');
        $redirectUri = (string) config('services.github.redirect_uri');

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            Log::warning('GitHub OAuth callback aborted because configuration is incomplete.', [
                'session' => $request->session()->getId(),
            ]);
            return redirect('/');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->post('https://github.com/login/oauth/access_token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => (string) $request->query('code'),
                    'redirect_uri' => $redirectUri,
                    'state' => $state,
                ])
                ->throw()
                ->json();

            $token = (string) ($response['access_token'] ?? '');

            if ($token === '') {
                Log::warning('GitHub OAuth callback did not receive an access token.', [
                    'response_keys' => array_keys($response),
                ]);
                return redirect('/');
            }

            $profile = $gitHubApiClient->authenticatedProfile($token);
            $resolvedUsername = (string) ($profile['login'] ?? '');

            if ($resolvedUsername === '') {
                Log::warning('GitHub OAuth callback did not resolve an authenticated username.');
                return redirect('/');
            }

            $connection = GitHubConnection::query()->updateOrCreate(
                ['github_username' => $resolvedUsername, 'user_id' => null],
                [
                    'analysis_mode' => 'public_private',
                    'access_token' => $token,
                    'sync_status' => 'idle',
                    'sync_error' => null,
                    'connected_at' => now(),
                ],
            );

            $request->session()->put('github.current_connection_id', $connection->id);
            $request->session()->put('github.current_username', $resolvedUsername);
            $request->session()->save();

            Log::info('GitHub OAuth login completed.', [
                'resolved_username' => $resolvedUsername,
                'connection_id' => $connection->id,
            ]);

            return redirect('/');
        } catch (RequestException $exception) {
            Log::error('GitHub OAuth callback failed while exchanging or using the access token.', [
                'status' => $exception->response?->status(),
                'message' => $exception->response?->json('message')
                    ?? $exception->response?->json('error_description')
                    ?? $exception->response?->json('error')
                    ?? $exception->getMessage(),
            ]);

            return redirect('/');
        } catch (Throwable $throwable) {
            Log::error('GitHub OAuth callback failed unexpectedly.', [
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return redirect('/');
        }
    }
}
