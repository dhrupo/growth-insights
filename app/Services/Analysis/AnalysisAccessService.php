<?php

namespace App\Services\Analysis;

use App\Models\AnalysisRun;
use App\Models\GitHubConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AnalysisAccessService
{
    public function currentConnection(Request $request): ?GitHubConnection
    {
        if (! $request->hasSession()) {
            return null;
        }

        $connectionId = $request->session()->get('github.current_connection_id');
        $githubUsername = (string) $request->session()->get('github.current_username', '');

        if (! $connectionId) {
            return null;
        }

        $query = GitHubConnection::query()->whereKey($connectionId);

        if ($githubUsername !== '') {
            $query->where('github_username', $githubUsername);
        }

        return $query->first();
    }

    public function connectionIsAccessible(Request $request, GitHubConnection $connection): bool
    {
        $currentConnection = $this->currentConnection($request);

        return $currentConnection !== null && $currentConnection->is($connection);
    }

    public function runIsAccessible(Request $request, AnalysisRun $run): bool
    {
        if ($this->isPublicRun($run)) {
            return true;
        }

        $currentConnection = $this->currentConnection($request);

        return $currentConnection !== null
            && $run->github_connection_id !== null
            && $run->github_connection_id === $currentConnection->id;
    }

    public function accessibleRun(Request $request, AnalysisRun $run): ?AnalysisRun
    {
        if (! $this->runIsAccessible($request, $run)) {
            return null;
        }

        return $run->loadMissing(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);
    }

    public function accessibleRunById(Request $request, int $analysisRunId): ?AnalysisRun
    {
        $run = $this->baseRunQuery()->find($analysisRunId);

        if (! $run instanceof AnalysisRun) {
            return null;
        }

        return $this->runIsAccessible($request, $run) ? $run : null;
    }

    public function latestAccessibleRunForUsername(Request $request, string $githubUsername): ?AnalysisRun
    {
        $currentConnection = $this->currentConnection($request);

        if ($currentConnection !== null && $currentConnection->github_username === $githubUsername) {
            $privateRun = $this->baseRunQuery()
                ->where('status', 'completed')
                ->where('github_connection_id', $currentConnection->id)
                ->latest('completed_at')
                ->latest('id')
                ->first();

            if ($privateRun instanceof AnalysisRun) {
                return $privateRun;
            }
        }

        return $this->latestPublicRunForUsername($githubUsername);
    }

    public function latestPublicRunForUsername(string $githubUsername): ?AnalysisRun
    {
        return $this->baseRunQuery()
            ->where('status', 'completed')
            ->where('github_username', $githubUsername)
            ->where('analysis_mode', 'public_only')
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    public function resolveDashboardRun(Request $request): ?AnalysisRun
    {
        if ($request->filled('analysis_run_id')) {
            return $this->accessibleRunById($request, $request->integer('analysis_run_id'));
        }

        if ($request->filled('github_username')) {
            return $this->latestAccessibleRunForUsername($request, $request->string('github_username')->toString());
        }

        $currentConnection = $this->currentConnection($request);

        if ($currentConnection === null) {
            return null;
        }

        return $this->baseRunQuery()
            ->where('status', 'completed')
            ->where('github_connection_id', $currentConnection->id)
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    private function baseRunQuery(): Builder
    {
        return AnalysisRun::query()->with(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);
    }

    private function isPublicRun(AnalysisRun $run): bool
    {
        return $run->analysis_mode === 'public_only';
    }
}
