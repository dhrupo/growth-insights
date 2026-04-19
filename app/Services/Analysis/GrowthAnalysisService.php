<?php

namespace App\Services\Analysis;

use App\Models\AnalysisMetricSnapshot;
use App\Models\AnalysisRecommendation;
use App\Models\AnalysisRun;
use App\Models\AnalysisWeeklyBucket;
use App\Models\GitHubConnection;
use App\Models\SkillSignal;
use App\Services\AI\GeminiInsightService;
use App\Services\GitHub\GitHubApiClient;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GrowthAnalysisService
{
    public function __construct(
        private readonly GitHubApiClient $github,
        private readonly GrowthScoreCalculator $scoreCalculator,
        private readonly WeeklyTimelineBuilder $weeklyTimelineBuilder,
        private readonly SkillSignalBuilder $skillSignalBuilder,
        private readonly RecommendationBuilder $recommendationBuilder,
        private readonly GeminiInsightService $geminiInsightService,
    ) {
    }

    public function analyzePublicUsername(string $githubUsername): AnalysisRun
    {
        return $this->analyze(
            githubUsername: $githubUsername,
            token: null,
            connection: null,
            analysisMode: 'public_only',
        );
    }

    public function syncConnection(GitHubConnection $connection): AnalysisRun
    {
        $token = $connection->access_token;

        return $this->analyze(
            githubUsername: $connection->github_username,
            token: $token,
            connection: $connection,
            analysisMode: $connection->analysis_mode ?: 'public_private',
        );
    }

    public function latestForUsername(string $githubUsername): ?AnalysisRun
    {
        return AnalysisRun::query()
            ->where('github_username', $githubUsername)
            ->latest('completed_at')
            ->latest('id')
            ->with(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations'])
            ->first();
    }

    public function latestForConnection(GitHubConnection $connection): ?AnalysisRun
    {
        return AnalysisRun::query()
            ->where('github_connection_id', $connection->id)
            ->latest('completed_at')
            ->latest('id')
            ->with(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations'])
            ->first();
    }

    public function connectionForUsername(string $githubUsername): ?GitHubConnection
    {
        return GitHubConnection::query()
            ->where('github_username', $githubUsername)
            ->latest('last_synced_at')
            ->latest('connected_at')
            ->latest('id')
            ->first();
    }

    private function analyze(string $githubUsername, ?string $token, ?GitHubConnection $connection, string $analysisMode): AnalysisRun
    {
        $windowDays = 56;
        $windowStart = now()->subDays($windowDays - 1)->startOfDay();
        $windowEnd = now()->endOfDay();
        $startedAt = now();

        $profile = $token ? $this->github->authenticatedProfile($token) : $this->github->publicProfile($githubUsername);
        $resolvedUsername = $profile['login'] ?? $githubUsername;
        $repos = $token ? $this->github->authenticatedRepositories($token) : $this->github->publicRepositories($resolvedUsername);
        $repos = collect($repos)
            ->filter(static fn (array $repo): bool => ! (bool) ($repo['archived'] ?? false))
            ->sortByDesc(static fn (array $repo): string => (string) ($repo['updated_at'] ?? ''))
            ->take(6)
            ->values();

        $facts = $this->buildFacts(
            githubUsername: $resolvedUsername,
            resolvedUsername: $resolvedUsername,
            profile: $profile,
            repositories: $repos,
            token: $token,
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            analysisMode: $analysisMode,
        );

        $metrics = $this->scoreCalculator->calculate($facts);
        $weeklyBuckets = $facts['weekly_buckets'];
        $momentumLabel = $this->weeklyTimelineBuilder->momentumLabel($weeklyBuckets);
        $skillSignals = $this->skillSignalBuilder->build($facts);
        $strengths = $this->buildStrengths($facts, $metrics, $momentumLabel, $skillSignals);
        $weaknesses = $this->buildWeaknesses($facts, $metrics, $momentumLabel, $skillSignals);
        $facts['strengths'] = $strengths;
        $facts['weaknesses'] = $weaknesses;

        $weeklyPlan = $this->recommendationBuilder->weeklyPlan($facts, $metrics, $skillSignals, $momentumLabel);
        $recommendations = $this->recommendationBuilder->build($facts, $metrics, $skillSignals, $momentumLabel);
        $evidenceSummary = $this->buildEvidenceSummary($facts, $metrics, $momentumLabel);
        $summary = $this->buildSummary($facts, $metrics, $momentumLabel, $strengths, $weaknesses);
        $context = $this->buildContext($facts, $metrics, $weeklyBuckets, $momentumLabel);
        $ruleBasedSections = [
            'summary' => $summary,
            'weekly_plan' => $weeklyPlan,
            'recommendations' => $recommendations,
        ];
        $aiEnhancement = $this->geminiInsightService->enhance([
            'profile' => $facts['profile'],
            'metrics' => $metrics,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'weekly_plan' => $weeklyPlan,
            'recommendations' => $recommendations,
            'skill_signals' => $skillSignals,
            'weekly_buckets' => $weeklyBuckets,
            'evidence_summary' => $evidenceSummary,
        ]);
        $weeklyPlanWithAi = $this->mergeWeeklyPlanEnhancement($weeklyPlan, $aiEnhancement['weekly_plan_notes'] ?? []);
        $recommendationsWithAi = $this->mergeRecommendationEnhancement($recommendations, $aiEnhancement['recommendation_notes'] ?? []);
        $aiEnhancement['merged_weekly_plan'] = $weeklyPlanWithAi;
        $aiEnhancement['merged_recommendations'] = $recommendationsWithAi;
        $aiEnhancement['rule_based_sections'] = $ruleBasedSections;

        $analysisRun = DB::transaction(function () use (
            $connection,
            $resolvedUsername,
            $analysisMode,
            $windowStart,
            $windowEnd,
            $startedAt,
            $metrics,
            $momentumLabel,
            $strengths,
            $weaknesses,
            $weeklyPlan,
            $context,
            $summary,
            $evidenceSummary,
            $facts,
            $weeklyBuckets,
            $skillSignals,
            $recommendations,
            $aiEnhancement,
        ): AnalysisRun {
            $run = AnalysisRun::create([
                'github_connection_id' => $connection?->id,
                'github_username' => $resolvedUsername,
                'analysis_mode' => $analysisMode,
                'status' => 'completed',
                'source_window_start' => $windowStart->toDateString(),
                'source_window_end' => $windowEnd->toDateString(),
                'started_at' => $startedAt,
                'completed_at' => now(),
                'overall_score' => $metrics['overall_score'],
                'confidence' => $metrics['confidence'],
                'momentum_label' => $momentumLabel,
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
                'weekly_plan' => $weeklyPlan,
                'context' => $context,
                'ai_status' => $aiEnhancement['status'] ?? 'not_requested',
                'ai_model' => $aiEnhancement['model'] ?? null,
                'ai_snapshot_hash' => $aiEnhancement['snapshot_hash'] ?? null,
                'ai_enhanced_at' => ($aiEnhancement['status'] ?? null) === 'enhanced' ? now() : null,
                'ai_error' => $aiEnhancement['error'] ?: null,
                'ai_enhancement' => $aiEnhancement,
                'summary' => $summary,
                'evidence_summary' => $evidenceSummary,
            ]);

            AnalysisMetricSnapshot::create([
                'analysis_run_id' => $run->id,
                'consistency_score' => $metrics['consistency_score'],
                'diversity_score' => $metrics['diversity_score'],
                'contribution_score' => $metrics['contribution_score'],
                'collaboration_score' => $facts['collaboration_score'],
                'testing_score' => $facts['testing_score'],
                'documentation_score' => $facts['documentation_score'],
                'momentum_score' => $facts['momentum_score'],
                'score_breakdown' => $metrics['score_breakdown'],
                'evidence' => $facts['metric_evidence'],
            ]);

            foreach ($weeklyBuckets as $bucket) {
                AnalysisWeeklyBucket::create([
                    'analysis_run_id' => $run->id,
                    'week_start' => $bucket['week_start'],
                    'week_end' => $bucket['week_end'],
                    'active_days' => $bucket['active_days'],
                    'commits_count' => $bucket['commits_count'],
                    'pull_requests_count' => $bucket['pull_requests_count'],
                    'issues_count' => $bucket['issues_count'],
                    'reviews_count' => $bucket['reviews_count'],
                    'momentum_label' => $bucket['momentum_label'],
                    'trend_direction' => $bucket['trend_direction'],
                    'evidence' => Arr::except($bucket['evidence'], ['total_events']),
                ]);
            }

            foreach ($skillSignals as $signal) {
                SkillSignal::create([
                    'analysis_run_id' => $run->id,
                    'skill_key' => $signal['skill_key'],
                    'score' => $signal['score'],
                    'confidence' => $signal['confidence'],
                    'evidence' => $signal['evidence'],
                    'notes' => $signal['notes'],
                ]);
            }

            foreach ($recommendations as $recommendation) {
                AnalysisRecommendation::create([
                    'analysis_run_id' => $run->id,
                    'category' => $recommendation['category'],
                    'title' => $recommendation['title'],
                    'body' => $recommendation['body'],
                    'sort_order' => $recommendation['sort_order'],
                    'metadata' => $recommendation['metadata'],
                ]);
            }

            return $run->load(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);
        });

        if ($connection !== null) {
            $connection->forceFill([
                'sync_status' => 'complete',
                'sync_error' => null,
                'last_synced_at' => now(),
                'github_username' => $resolvedUsername,
            ])->save();
        }

        return $analysisRun;
    }

    private function buildFacts(
        string $githubUsername,
        string $resolvedUsername,
        array $profile,
        Collection $repositories,
        ?string $token,
        Carbon $windowStart,
        Carbon $windowEnd,
        string $analysisMode,
    ): array {
        $languageBytes = [];
        $repositoryNames = [];
        $commitMessages = [];
        $pullRequestTitles = [];
        $issueTitles = [];
        $events = [];
        $reposTouched = [];
        $commitCount = 0;
        $pullRequestCount = 0;
        $issueCount = 0;
        $activeDays = [];
        $eventTypeDays = [];
        $globalPullRequests = $this->github->authorPullRequests($githubUsername, $windowStart, $token);
        $globalIssues = $this->github->authorIssues($githubUsername, $windowStart, $token);

        foreach ($repositories as $repository) {
            $fullName = $repository['full_name'] ?? null;

            if (! $fullName) {
                continue;
            }

            $repositoryNames[] = $fullName;

            foreach ($this->github->repositoryLanguages($fullName, $token) as $language => $bytes) {
                $language = strtolower((string) $language);
                $languageBytes[$language] = ($languageBytes[$language] ?? 0) + (int) $bytes;
            }

            $commits = $this->github->repositoryCommits($fullName, $githubUsername, $windowStart, $token);
            foreach ($commits as $commit) {
                $date = data_get($commit, 'commit.author.date') ?? data_get($commit, 'commit.committer.date');

                if (! $date) {
                    continue;
                }

                $commitCount++;
                $reposTouched[$fullName] = true;
                $dateString = Carbon::parse($date)->toDateString();
                $activeDays[$dateString] = true;
                $eventTypeDays['commit'][$dateString] = true;
                $commitMessages[] = (string) data_get($commit, 'commit.message', '');
                $events[] = [
                    'type' => 'commit',
                    'date' => $dateString,
                    'repo' => $fullName,
                    'message' => (string) data_get($commit, 'commit.message', ''),
                ];
            }
        }

        foreach ($globalPullRequests as $pullRequest) {
            $date = data_get($pullRequest, 'created_at');

            if (! $date) {
                continue;
            }

            $repoFullName = (string) str((string) data_get($pullRequest, 'repository_url', ''))
                ->after('/repos/');

            $pullRequestCount++;
            if ($repoFullName !== '') {
                $reposTouched[$repoFullName] = true;
            }
            $dateString = Carbon::parse($date)->toDateString();
            $activeDays[$dateString] = true;
            $eventTypeDays['pull_request'][$dateString] = true;
            $pullRequestTitles[] = (string) data_get($pullRequest, 'title', '');
            $events[] = [
                'type' => 'pull_request',
                'date' => $dateString,
                'repo' => $repoFullName,
                'message' => (string) data_get($pullRequest, 'title', ''),
            ];
        }

        foreach ($globalIssues as $issue) {
            if (isset($issue['pull_request'])) {
                continue;
            }

            $date = data_get($issue, 'created_at');

            if (! $date) {
                continue;
            }

            $repoFullName = (string) str((string) data_get($issue, 'repository_url', ''))
                ->after('/repos/');

            $issueCount++;
            if ($repoFullName !== '') {
                $reposTouched[$repoFullName] = true;
            }
            $dateString = Carbon::parse($date)->toDateString();
            $activeDays[$dateString] = true;
            $eventTypeDays['issue'][$dateString] = true;
            $issueTitles[] = (string) data_get($issue, 'title', '');
            $events[] = [
                'type' => 'issue',
                'date' => $dateString,
                'repo' => $repoFullName,
                'message' => (string) data_get($issue, 'title', ''),
            ];
        }

        $totalWeeks = max(1, Carbon::parse($windowStart)->diffInWeeks(Carbon::parse($windowEnd)) + 1);
        $activeWeeks = collect($events)
            ->groupBy(static fn (array $event) => Carbon::parse($event['date'])->startOfWeek()->toDateString())
            ->filter(static fn (Collection $weekEvents): bool => $weekEvents->isNotEmpty())
            ->count();

        $totalLanguageBytes = array_sum($languageBytes);
        $languageShares = [];

        foreach ($languageBytes as $language => $bytes) {
            $languageShares[$language] = $totalLanguageBytes > 0 ? round($bytes / $totalLanguageBytes, 4) : 0.0;
        }

        arsort($languageBytes);
        $topLanguages = array_slice($languageBytes, 0, 5, true);
        $languageEntropy = $this->languageEntropy($languageShares);
        $languageBreadth = $this->languageBreadth($languageShares);

        $weeklyBuckets = $this->weeklyTimelineBuilder->build([
            'window_start' => $windowStart->toDateString(),
            'window_end' => $windowEnd->toDateString(),
            'events' => $events,
        ]);

        $momentumScore = $this->momentumScore($weeklyBuckets);
        $collaborationScore = $this->collaborationScore($pullRequestCount, $issueCount, count($reposTouched));
        $testingScore = $this->keywordScore($commitMessages, $pullRequestTitles, ['test', 'tests', 'pest', 'phpunit', 'jest', 'coverage']);
        $documentationScore = $this->keywordScore($commitMessages, $issueTitles, ['docs', 'documentation', 'readme', 'guide', 'doc']);

        return [
            'github_username' => $githubUsername,
            'window_start' => $windowStart->toDateString(),
            'window_end' => $windowEnd->toDateString(),
            'window_days' => $windowStart->diffInDays($windowEnd) + 1,
            'total_weeks' => $totalWeeks,
            'active_weeks' => $activeWeeks,
            'active_days' => count($activeDays),
            'commits_count' => $commitCount,
            'pull_requests_count' => $pullRequestCount,
            'issues_count' => $issueCount,
            'repos_touched' => count($reposTouched),
            'repo_names' => $repositoryNames,
            'language_shares' => $languageShares,
            'language_entropy' => $languageEntropy,
            'language_breadth' => $languageBreadth,
            'commit_messages' => array_slice($commitMessages, 0, 30),
            'pull_request_titles' => array_slice($pullRequestTitles, 0, 30),
            'issue_titles' => array_slice($issueTitles, 0, 30),
            'events' => $events,
            'weekly_buckets' => $weeklyBuckets,
            'collaboration_score' => $collaborationScore,
            'testing_score' => $testingScore,
            'documentation_score' => $documentationScore,
            'momentum_score' => $momentumScore,
            'metric_evidence' => [
                'repos_touched' => count($reposTouched),
                'language_distribution' => array_slice($languageShares, 0, 5, true),
                'top_languages' => $topLanguages,
                'commit_count' => $commitCount,
                'pull_request_count' => $pullRequestCount,
                'issue_count' => $issueCount,
                'active_days' => count($activeDays),
            ],
            'profile' => [
                'login' => $resolvedUsername ?: $githubUsername,
                'name' => $profile['name'] ?? null,
                'public_repos' => (int) ($profile['public_repos'] ?? 0),
                'followers' => (int) ($profile['followers'] ?? 0),
                'following' => (int) ($profile['following'] ?? 0),
                'html_url' => $profile['html_url'] ?? null,
                'analysis_mode' => $analysisMode,
            ],
        ];
    }

    private function buildStrengths(array $facts, array $metrics, string $momentumLabel, array $skillSignals): array
    {
        $strengths = [];

        if ($metrics['consistency_score'] >= 65) {
            $strengths[] = [
                'key' => 'consistency',
                'title' => 'Strong contribution cadence',
                'score' => $metrics['consistency_score'],
                'evidence' => sprintf('%d active weeks and %d active days in the analyzed window.', $facts['active_weeks'], $facts['active_days']),
            ];
        }

        if ($metrics['diversity_score'] >= 60) {
            $strengths[] = [
                'key' => 'diversity',
                'title' => 'Broad enough signal mix',
                'score' => $metrics['diversity_score'],
                'evidence' => sprintf('%d repositories touched and %d distinct languages used.', $facts['repos_touched'], count($facts['language_shares'])),
            ];
        }

        if ($metrics['contribution_score'] >= 60) {
            $strengths[] = [
                'key' => 'contribution',
                'title' => 'Visible shipping activity',
                'score' => $metrics['contribution_score'],
                'evidence' => sprintf('%d commits, %d PRs, and %d issues in the analysis window.', $facts['commits_count'], $facts['pull_requests_count'], $facts['issues_count']),
            ];
        }

        if ($momentumLabel !== 'declining') {
            $strengths[] = [
                'key' => 'momentum',
                'title' => 'Momentum is still recoverable',
                'score' => $facts['momentum_score'],
                'evidence' => sprintf('Weekly momentum is labeled %s.', $momentumLabel),
            ];
        }

        if ($skillSignals !== []) {
            $topSignal = collect($skillSignals)->sortByDesc('score')->first();
            $strengths[] = [
                'key' => $topSignal['skill_key'],
                'title' => ucfirst(str_replace('_', ' ', $topSignal['skill_key'])) . ' signal',
                'score' => $topSignal['score'],
                'evidence' => $topSignal['notes'],
            ];
        }

        return array_slice($strengths, 0, 4);
    }

    private function buildWeaknesses(array $facts, array $metrics, string $momentumLabel, array $skillSignals): array
    {
        $weaknesses = [];

        if ($metrics['consistency_score'] < 55) {
            $weaknesses[] = [
                'key' => 'consistency',
                'title' => 'Inconsistent activity pattern',
                'score' => $metrics['consistency_score'],
                'evidence' => sprintf('%d active weeks out of %d.', $facts['active_weeks'], $facts['total_weeks']),
            ];
        }

        if ($metrics['diversity_score'] < 55) {
            $weaknesses[] = [
                'key' => 'diversity',
                'title' => 'Narrow language or repo spread',
                'score' => $metrics['diversity_score'],
                'evidence' => sprintf('Activity is concentrated in %d repositories and %d languages.', $facts['repos_touched'], count($facts['language_shares'])),
            ];
        }

        if ($metrics['contribution_score'] < 55) {
            $weaknesses[] = [
                'key' => 'contribution',
                'title' => 'Lower shipping volume',
                'score' => $metrics['contribution_score'],
                'evidence' => sprintf('%d commits, %d PRs, and %d issues are below the desired activity threshold.', $facts['commits_count'], $facts['pull_requests_count'], $facts['issues_count']),
            ];
        }

        if ($momentumLabel === 'declining') {
            $weaknesses[] = [
                'key' => 'momentum',
                'title' => 'Activity is trending down',
                'score' => $facts['momentum_score'],
                'evidence' => 'Recent weekly activity is weaker than the prior period.',
            ];
        }

        if ($skillSignals !== []) {
            $lowestSignal = collect($skillSignals)->sortBy('score')->first();

            if (($lowestSignal['score'] ?? 0) < 45) {
                $weaknesses[] = [
                    'key' => $lowestSignal['skill_key'],
                    'title' => ucfirst(str_replace('_', ' ', $lowestSignal['skill_key'])) . ' signal is weak',
                    'score' => $lowestSignal['score'],
                    'evidence' => $lowestSignal['notes'],
                ];
            }
        }

        return array_slice($weaknesses, 0, 4);
    }

    private function buildEvidenceSummary(array $facts, array $metrics, string $momentumLabel): string
    {
        return implode(' ', array_filter([
            sprintf('%d active weeks, %d active days, %d commits, %d PRs, and %d issues were analyzed.', $facts['active_weeks'], $facts['active_days'], $facts['commits_count'], $facts['pull_requests_count'], $facts['issues_count']),
            sprintf('Top languages: %s.', collect($facts['language_shares'])->sortDesc()->keys()->take(3)->implode(', ')),
            sprintf('Momentum label: %s.', $momentumLabel),
            sprintf('Overall score %.2f with confidence %.2f.', $metrics['overall_score'], $metrics['confidence']),
        ]));
    }

    private function buildSummary(array $facts, array $metrics, string $momentumLabel, array $strengths, array $weaknesses): string
    {
        $topStrength = $strengths[0]['title'] ?? 'balanced activity';
        $topWeakness = $weaknesses[0]['title'] ?? 'no major weakness flagged';

        return sprintf(
            'Growth score %.2f with %s and %s. Momentum is %s.',
            $metrics['overall_score'],
            $topStrength,
            $topWeakness,
            $momentumLabel,
        );
    }

    private function buildContext(array $facts, array $metrics, array $weeklyBuckets, string $momentumLabel): array
    {
        return [
            'profile' => $facts['profile'],
            'activity' => [
                'repos_touched' => $facts['repos_touched'],
                'commits_count' => $facts['commits_count'],
                'pull_requests_count' => $facts['pull_requests_count'],
                'issues_count' => $facts['issues_count'],
                'active_days' => $facts['active_days'],
                'active_weeks' => $facts['active_weeks'],
            ],
            'languages' => [
                'shares' => $facts['language_shares'],
                'entropy' => $facts['language_entropy'],
                'breadth' => $facts['language_breadth'],
            ],
            'metrics' => $metrics,
            'momentum' => [
                'label' => $momentumLabel,
                'weekly_buckets' => array_map(static fn (array $bucket) => Arr::except($bucket, ['total_events']), $weeklyBuckets),
            ],
        ];
    }

    private function mergeWeeklyPlanEnhancement(array $weeklyPlan, array $weeklyPlanNotes): array
    {
        $notesByIndex = collect($weeklyPlanNotes)->keyBy('index');

        return collect($weeklyPlan)->values()->map(function (array $item, int $index) use ($notesByIndex): array {
            $note = $notesByIndex->get($index);

            if ($note === null) {
                $item['ai_note'] = null;

                return $item;
            }

            $item['ai_note'] = $note['ai_note'] ?? null;

            return $item;
        })->all();
    }

    private function mergeRecommendationEnhancement(array $recommendations, array $recommendationNotes): array
    {
        $notesByIndex = collect($recommendationNotes)->keyBy('index');

        return collect($recommendations)->values()->map(function (array $item, int $index) use ($notesByIndex): array {
            $note = $notesByIndex->get($index);

            $item['ai_note'] = $note['ai_note'] ?? null;
            $item['ai_confidence'] = $note['confidence'] ?? null;

            return $item;
        })->all();
    }

    private function momentumScore(array $weeklyBuckets): float
    {
        if ($weeklyBuckets === []) {
            return 0.0;
        }

        $recent = collect($weeklyBuckets)->slice(max(0, count($weeklyBuckets) - 2))->sum('total_events');
        $previous = collect($weeklyBuckets)->slice(max(0, count($weeklyBuckets) - 4), 2)->sum('total_events');

        if ($recent === 0 && $previous === 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, (($recent + 1) / ($previous + 1)) * 50));
    }

    private function collaborationScore(int $pullRequests, int $issues, int $reposTouched): float
    {
        return max(0.0, min(100.0, ($pullRequests * 10) + ($issues * 5) + ($reposTouched * 4)));
    }

    private function keywordScore(array $primary, array $secondary, array $keywords): float
    {
        $hits = 0;

        foreach (array_merge($primary, $secondary) as $entry) {
            $haystack = strtolower((string) $entry);

            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $hits++;
                    break;
                }
            }
        }

        return max(0.0, min(100.0, $hits * 12));
    }

    private function languageEntropy(array $shares): float
    {
        if ($shares === []) {
            return 0.0;
        }

        $sumSquares = 0.0;

        foreach ($shares as $share) {
            $sumSquares += ((float) $share) ** 2;
        }

        return round(max(0.0, min(100.0, (1.0 - $sumSquares) * 120)), 2);
    }

    private function languageBreadth(array $shares): float
    {
        return round(max(0.0, min(100.0, count(array_filter($shares, static fn ($share) => $share > 0)) * 16)), 2);
    }
}
