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
        $historyWindowDays = 365;
        $windowDays = 56;
        $historyWindowStart = now()->subDays($historyWindowDays - 1)->startOfDay();
        $windowStart = now()->subDays($windowDays - 1)->startOfDay();
        $windowEnd = now()->endOfDay();
        $startedAt = now();

        $profile = $token ? $this->github->authenticatedProfile($token) : $this->github->publicProfile($githubUsername);
        $resolvedUsername = $profile['login'] ?? $githubUsername;
        $repos = $token ? $this->github->authenticatedRepositories($token) : $this->github->publicRepositories($resolvedUsername);
        $repos = collect($repos)
            ->filter(static fn (array $repo): bool => ! (bool) ($repo['archived'] ?? false))
            ->sortByDesc(static fn (array $repo): string => (string) ($repo['updated_at'] ?? ''))
            ->take(10)
            ->values();

        $historyFacts = $this->buildFacts(
            githubUsername: $resolvedUsername,
            resolvedUsername: $resolvedUsername,
            profile: $profile,
            repositories: $repos,
            token: $token,
            windowStart: $historyWindowStart,
            windowEnd: $windowEnd,
            analysisMode: $analysisMode,
        );

        $facts = $this->buildWindowFacts($historyFacts, $windowStart, $windowEnd);

        $metrics = $this->scoreCalculator->calculate($facts);
        $weeklyBuckets = $facts['weekly_buckets'];
        $momentumLabel = $this->weeklyTimelineBuilder->momentumLabel($weeklyBuckets);
        $skillSignals = $this->skillSignalBuilder->build($facts);
        $trajectory = $this->buildTrajectoryWindows($historyFacts, $windowEnd);
        $strengths = $this->buildStrengths($facts, $metrics, $momentumLabel, $skillSignals);
        $weaknesses = $this->buildWeaknesses($facts, $metrics, $momentumLabel, $skillSignals);
        $facts['strengths'] = $strengths;
        $facts['weaknesses'] = $weaknesses;
        $contributionStyle = $this->buildContributionStyle($facts, $metrics, $skillSignals);
        $visibilityAdvice = $this->buildVisibilityAdvice($facts, $metrics, $skillSignals, $momentumLabel);
        $suggestedRepositories = $this->buildSuggestedRepositories($facts, $resolvedUsername, $token);

        $weeklyPlan = $this->recommendationBuilder->weeklyPlan($facts, $metrics, $skillSignals, $momentumLabel);
        $thirtyDayPlan = $this->recommendationBuilder->monthlyPlan($facts, $metrics, $skillSignals, $momentumLabel);
        $recommendations = $this->recommendationBuilder->build($facts, $metrics, $skillSignals, $momentumLabel);
        $evidenceSummary = $this->buildEvidenceSummary($facts, $metrics, $momentumLabel);
        $summary = $this->buildSummary($facts, $metrics, $momentumLabel, $strengths, $weaknesses);
        $context = $this->buildContext(
            $facts,
            $metrics,
            $weeklyBuckets,
            $momentumLabel,
            $historyFacts['analyzed_repositories'] ?? [],
            $trajectory,
            $contributionStyle,
            $visibilityAdvice,
            $suggestedRepositories,
            $thirtyDayPlan,
        );
        $ruleBasedSections = [
            'summary' => $summary,
            'weekly_plan' => $weeklyPlan,
            'thirty_day_plan' => $thirtyDayPlan,
            'recommendations' => $recommendations,
        ];
        $aiEnhancement = $this->geminiInsightService->enhance([
            'profile' => $facts['profile'],
            'metrics' => $metrics,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'weekly_plan' => $weeklyPlan,
            'thirty_day_plan' => $thirtyDayPlan,
            'recommendations' => $recommendations,
            'skill_signals' => $skillSignals,
            'weekly_buckets' => $weeklyBuckets,
            'evidence_summary' => $evidenceSummary,
            'analyzed_repositories' => $historyFacts['analyzed_repositories'] ?? [],
            'repo_summary' => $historyFacts['repo_summary'] ?? [],
            'trend_windows' => $trajectory,
            'contribution_style' => $contributionStyle,
            'visibility_advice' => $visibilityAdvice,
            'suggested_repositories' => $suggestedRepositories,
        ]);
        $weeklyPlanWithAi = $this->mergeWeeklyPlanEnhancement($weeklyPlan, $aiEnhancement['weekly_plan_notes'] ?? []);
        $thirtyDayPlanWithAi = $this->mergeThirtyDayPlanEnhancement($thirtyDayPlan, $aiEnhancement['thirty_day_plan_notes'] ?? []);
        $recommendationsWithAi = $this->mergeRecommendationEnhancement($recommendations, $aiEnhancement['recommendation_notes'] ?? []);
        $aiEnhancement['merged_weekly_plan'] = $weeklyPlanWithAi;
        $aiEnhancement['merged_thirty_day_plan'] = $thirtyDayPlanWithAi;
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

            $primaryLanguage = strtolower((string) ($repository['language'] ?? ''));
            if ($primaryLanguage !== '') {
                $languageBytes[$primaryLanguage] = ($languageBytes[$primaryLanguage] ?? 0) + max(1, (int) ($repository['size'] ?? 1));
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
            'commit_records' => collect($events)->where('type', 'commit')->map(fn (array $event): array => [
                'date' => $event['date'],
                'repo' => $event['repo'],
                'message' => $event['message'],
            ])->values()->all(),
            'pull_request_records' => collect($events)->where('type', 'pull_request')->map(fn (array $event): array => [
                'date' => $event['date'],
                'repo' => $event['repo'],
                'message' => $event['message'],
            ])->values()->all(),
            'issue_records' => collect($events)->where('type', 'issue')->map(fn (array $event): array => [
                'date' => $event['date'],
                'repo' => $event['repo'],
                'message' => $event['message'],
            ])->values()->all(),
            'events' => $events,
            'weekly_buckets' => $weeklyBuckets,
            'collaboration_score' => $collaborationScore,
            'testing_score' => $testingScore,
            'documentation_score' => $documentationScore,
            'momentum_score' => $momentumScore,
            'analyzed_repositories' => $this->buildAnalyzedRepositories($repositories, $events, $languageBytes, $windowEnd),
            'repo_summary' => [
                'sampled_count' => $repositories->count(),
                'repos_touched' => count($reposTouched),
                'top_languages' => collect($languageShares)->sortDesc()->keys()->take(3)->values()->all(),
            ],
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

    private function buildWindowFacts(array $facts, Carbon $windowStart, Carbon $windowEnd): array
    {
        $events = collect($facts['events'] ?? [])->filter(function (array $event) use ($windowStart, $windowEnd): bool {
            $date = Carbon::parse($event['date']);

            return $date->betweenIncluded($windowStart, $windowEnd);
        })->values();

        $activeDays = $events->pluck('date')->unique()->count();
        $reposTouched = $events->pluck('repo')->filter()->unique()->count();
        $commitCount = $events->where('type', 'commit')->count();
        $pullRequestCount = $events->where('type', 'pull_request')->count();
        $issueCount = $events->where('type', 'issue')->count();
        $weeklyBuckets = $this->weeklyTimelineBuilder->build([
            'window_start' => $windowStart->toDateString(),
            'window_end' => $windowEnd->toDateString(),
            'events' => $events->all(),
        ]);

        $activeWeeks = collect($weeklyBuckets)
            ->filter(static fn (array $bucket): bool => ($bucket['total_events'] ?? 0) > 0)
            ->count();

        $commitMessages = collect($facts['commit_records'] ?? [])
            ->filter(fn (array $record): bool => Carbon::parse($record['date'])->betweenIncluded($windowStart, $windowEnd))
            ->pluck('message')
            ->take(30)
            ->all();

        $pullRequestTitles = collect($facts['pull_request_records'] ?? [])
            ->filter(fn (array $record): bool => Carbon::parse($record['date'])->betweenIncluded($windowStart, $windowEnd))
            ->pluck('message')
            ->take(30)
            ->all();

        $issueTitles = collect($facts['issue_records'] ?? [])
            ->filter(fn (array $record): bool => Carbon::parse($record['date'])->betweenIncluded($windowStart, $windowEnd))
            ->pluck('message')
            ->take(30)
            ->all();

        $windowFacts = $facts;
        $windowFacts['window_start'] = $windowStart->toDateString();
        $windowFacts['window_end'] = $windowEnd->toDateString();
        $windowFacts['window_days'] = $windowStart->diffInDays($windowEnd) + 1;
        $windowFacts['total_weeks'] = max(1, $windowStart->diffInWeeks($windowEnd) + 1);
        $windowFacts['active_weeks'] = $activeWeeks;
        $windowFacts['active_days'] = $activeDays;
        $windowFacts['commits_count'] = $commitCount;
        $windowFacts['pull_requests_count'] = $pullRequestCount;
        $windowFacts['issues_count'] = $issueCount;
        $windowFacts['repos_touched'] = $reposTouched;
        $windowFacts['commit_messages'] = $commitMessages;
        $windowFacts['pull_request_titles'] = $pullRequestTitles;
        $windowFacts['issue_titles'] = $issueTitles;
        $windowFacts['events'] = $events->all();
        $windowFacts['weekly_buckets'] = $weeklyBuckets;
        $windowFacts['collaboration_score'] = $this->collaborationScore($pullRequestCount, $issueCount, $reposTouched);
        $windowFacts['testing_score'] = $this->keywordScore($commitMessages, $pullRequestTitles, ['test', 'tests', 'pest', 'phpunit', 'jest', 'coverage']);
        $windowFacts['documentation_score'] = $this->keywordScore($commitMessages, $issueTitles, ['docs', 'documentation', 'readme', 'guide', 'doc']);
        $windowFacts['momentum_score'] = $this->momentumScore($weeklyBuckets);
        $windowFacts['metric_evidence'] = [
            'repos_touched' => $reposTouched,
            'language_distribution' => array_slice($facts['language_shares'] ?? [], 0, 5, true),
            'commit_count' => $commitCount,
            'pull_request_count' => $pullRequestCount,
            'issue_count' => $issueCount,
            'active_days' => $activeDays,
        ];
        $windowFacts['analyzed_repositories'] = $this->filterRepositoriesForWindow(
            $facts['analyzed_repositories'] ?? [],
            $events->all(),
            $windowStart,
            $windowEnd,
        );

        return $windowFacts;
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
                $evidence = $lowestSignal['evidence'] ?? [];
                $confidence = round((float) ($lowestSignal['confidence'] ?? 0));
                $languageShare = isset($evidence['language_share'])
                    ? sprintf('Language share %.1f%%.', ((float) $evidence['language_share']) * 100)
                    : null;
                $keywordHits = isset($evidence['keyword_hits'])
                    ? sprintf('Keyword hits %d.', (int) $evidence['keyword_hits'])
                    : null;

                $weaknesses[] = [
                    'key' => $lowestSignal['skill_key'],
                    'title' => ucfirst(str_replace('_', ' ', $lowestSignal['skill_key'])) . ' signal is limited',
                    'score' => $lowestSignal['score'],
                    'evidence' => trim(implode(' ', array_filter([
                        $lowestSignal['notes'] ?? '',
                        $languageShare,
                        $keywordHits,
                        sprintf('Confidence %d%%.', $confidence),
                    ]))),
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

    private function buildContext(
        array $facts,
        array $metrics,
        array $weeklyBuckets,
        string $momentumLabel,
        array $analyzedRepositories,
        array $trajectory,
        array $contributionStyle,
        array $visibilityAdvice,
        array $suggestedRepositories,
        array $thirtyDayPlan
    ): array
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
            'repositories' => $analyzedRepositories,
            'trajectory' => $trajectory,
            'contribution_style' => $contributionStyle,
            'visibility_advice' => $visibilityAdvice,
            'suggested_repositories' => $suggestedRepositories,
            'thirty_day_plan' => $thirtyDayPlan,
            'credibility_notice' => sprintf(
                'Insights are based on %s GitHub activity from the connected account. Private work is only included when GitHub authorization allows it.',
                ($facts['profile']['analysis_mode'] ?? 'public_only') === 'public_private' ? 'public and private' : 'public'
            ),
        ];
    }

    private function buildAnalyzedRepositories(Collection $repositories, array $events, array $languageBytes, Carbon $windowEnd): array
    {
        $eventsByRepo = collect($events)->groupBy('repo');
        $topLanguages = array_keys(array_slice($languageBytes, 0, 3, true));
        $knownRepositories = $repositories->keyBy(fn (array $repository): string => (string) ($repository['full_name'] ?? ''));

        $baseRepositories = $repositories->map(function (array $repository) use ($eventsByRepo, $topLanguages, $windowEnd): array {
            $fullName = (string) ($repository['full_name'] ?? '');
            $repoEvents = collect($eventsByRepo->get($fullName, []));
            $lastActivity = $repoEvents->max('date') ?: ($repository['pushed_at'] ?? $repository['updated_at'] ?? null);

            return [
                'full_name' => $fullName,
                'name' => (string) ($repository['name'] ?? $fullName),
                'html_url' => $repository['html_url'] ?? null,
                'description' => $repository['description'] ?? null,
                'visibility' => (bool) ($repository['private'] ?? false) ? 'private' : 'public',
                'language' => $repository['language'] ?? null,
                'updated_at' => $repository['updated_at'] ?? null,
                'pushed_at' => $repository['pushed_at'] ?? null,
                'stars' => (int) ($repository['stargazers_count'] ?? 0),
                'forks' => (int) ($repository['forks_count'] ?? 0),
                'active_days' => $repoEvents->pluck('date')->unique()->count(),
                'commit_count' => $repoEvents->where('type', 'commit')->count(),
                'pull_request_count' => $repoEvents->where('type', 'pull_request')->count(),
                'issue_count' => $repoEvents->where('type', 'issue')->count(),
                'last_activity' => $lastActivity,
                'signals' => [
                    'language_mix' => in_array(strtolower((string) ($repository['language'] ?? '')), $topLanguages, true),
                    'contribution' => $repoEvents->isNotEmpty(),
                    'skill_inference' => $repoEvents->isNotEmpty() || ! empty($repository['language']),
                ],
            ];
        });

        $eventOnlyRepositories = $eventsByRepo
            ->keys()
            ->filter(fn ($fullName): bool => filled($fullName) && ! $knownRepositories->has($fullName))
            ->map(function (string $fullName) use ($eventsByRepo, $topLanguages): array {
                $repoEvents = collect($eventsByRepo->get($fullName, []));
                $language = null;

                foreach ($topLanguages as $topLanguage) {
                    if (str_contains(strtolower($fullName), strtolower($topLanguage))) {
                        $language = ucfirst($topLanguage);
                        break;
                    }
                }

                return [
                    'full_name' => $fullName,
                    'name' => (string) str($fullName)->afterLast('/'),
                    'html_url' => filled($fullName) ? 'https://github.com/'.$fullName : null,
                    'description' => null,
                    'visibility' => 'public',
                    'language' => $language,
                    'updated_at' => null,
                    'pushed_at' => null,
                    'stars' => 0,
                    'forks' => 0,
                    'active_days' => $repoEvents->pluck('date')->unique()->count(),
                    'commit_count' => $repoEvents->where('type', 'commit')->count(),
                    'pull_request_count' => $repoEvents->where('type', 'pull_request')->count(),
                    'issue_count' => $repoEvents->where('type', 'issue')->count(),
                    'last_activity' => $repoEvents->max('date'),
                    'signals' => [
                        'language_mix' => false,
                        'contribution' => true,
                        'skill_inference' => true,
                    ],
                ];
            });

        return $baseRepositories
            ->concat($eventOnlyRepositories)
            ->sortByDesc(function (array $repository): array {
                $activity = (int) (($repository['commit_count'] ?? 0) + ($repository['pull_request_count'] ?? 0) + ($repository['issue_count'] ?? 0));

                return [$activity, (string) ($repository['last_activity'] ?? '')];
            })
            ->values()
            ->all();
    }

    private function filterRepositoriesForWindow(array $repositories, array $events, Carbon $windowStart, Carbon $windowEnd): array
    {
        $eventsByRepo = collect($events)->groupBy('repo');

        return collect($repositories)->map(function (array $repository) use ($eventsByRepo): array {
            $fullName = (string) ($repository['full_name'] ?? '');
            $repoEvents = collect($eventsByRepo->get($fullName, []));

            $repository['active_days'] = $repoEvents->pluck('date')->unique()->count();
            $repository['commit_count'] = $repoEvents->where('type', 'commit')->count();
            $repository['pull_request_count'] = $repoEvents->where('type', 'pull_request')->count();
            $repository['issue_count'] = $repoEvents->where('type', 'issue')->count();
            $repository['last_activity'] = $repoEvents->max('date') ?: $repository['last_activity'] ?? $repository['pushed_at'] ?? null;
            $repository['in_window'] = $repoEvents->isNotEmpty();

            return $repository;
        })->filter(function (array $repository): bool {
            return ($repository['in_window'] ?? false)
                || (($repository['signals']['language_mix'] ?? false) === true);
        })->values()->all();
    }

    private function buildContributionStyle(array $facts, array $metrics, array $skillSignals): array
    {
        $collaborationScore = (float) ($facts['collaboration_score'] ?? 0);
        $contributionScore = (float) ($metrics['contribution_score'] ?? 0);
        $diversityScore = (float) ($metrics['diversity_score'] ?? 0);
        $testingScore = (float) ($facts['testing_score'] ?? 0);

        $label = 'Builder';
        $summary = 'You are showing a build-and-ship profile with more visible output than review or maintenance signals.';
        $evidence = [
            sprintf('%d commits and %d repos touched in the current window.', $facts['commits_count'], $facts['repos_touched']),
        ];

        if ($collaborationScore >= 70) {
            $label = 'Collaborator';
            $summary = 'Your visible profile is strongest when work is reviewable across pull requests and multiple repositories.';
            $evidence[] = sprintf('%d PRs and %d touched repos are driving collaboration visibility.', $facts['pull_requests_count'], $facts['repos_touched']);
        } elseif ($testingScore >= 45) {
            $label = 'Maintainer';
            $summary = 'Your profile is leaning toward maintainable, quality-aware work rather than pure shipping volume.';
            $evidence[] = sprintf('Testing signal %.0f and documentation signal %.0f support a maintainer profile.', $facts['testing_score'], $facts['documentation_score']);
        } elseif ($diversityScore >= 65 && count($skillSignals) >= 3) {
            $label = 'Generalist';
            $summary = 'Your visible work spans enough tools and surfaces to read as a generalist profile.';
            $evidence[] = sprintf('Diversity score %.1f with %d visible skill signals.', $diversityScore, count($skillSignals));
        } elseif ($contributionScore >= 65) {
            $label = 'Specialist';
            $summary = 'Your strongest visible work clusters around a narrower contribution pattern and a primary signal area.';
            $evidence[] = sprintf('Contribution score %.1f is higher than diversity score %.1f.', $contributionScore, $diversityScore);
        }

        return [
            'label' => $label,
            'summary' => $summary,
            'confidence' => $this->confidenceLabel((float) ($metrics['confidence'] ?? 0)),
            'evidence' => array_values(array_filter($evidence)),
        ];
    }

    private function buildVisibilityAdvice(array $facts, array $metrics, array $skillSignals, string $momentumLabel): array
    {
        $actions = [];

        if (($facts['pull_requests_count'] ?? 0) < 4) {
            $actions[] = [
                'action' => 'Open smaller, reviewable pull requests more often.',
                'why' => 'PRs are easier for other engineers to notice and evaluate than isolated commit volume.',
                'evidence' => sprintf('Only %d visible PRs were found in the current analysis window.', $facts['pull_requests_count'] ?? 0),
            ];
        }

        if (($facts['repos_touched'] ?? 0) < 3) {
            $actions[] = [
                'action' => 'Spread one contribution each month into a second or third repository.',
                'why' => 'Cross-repo activity increases visible breadth and makes your work easier to discover.',
                'evidence' => sprintf('Current activity is concentrated in %d repositories.', $facts['repos_touched'] ?? 0),
            ];
        }

        if (($facts['testing_score'] ?? 0) < 25) {
            $actions[] = [
                'action' => 'Attach tests or validation paths to visible work whenever possible.',
                'why' => 'Tests make contributions easier to trust and signal engineering maturity quickly.',
                'evidence' => sprintf('Testing signal is currently %.0f.', $facts['testing_score'] ?? 0),
            ];
        }

        if ($momentumLabel === 'declining') {
            $actions[] = [
                'action' => 'Reduce scope and keep a weekly public artifact even if it is small.',
                'why' => 'A steady cadence makes you easier to notice than occasional large bursts followed by silence.',
                'evidence' => 'Recent weekly activity is weaker than the prior period.',
            ];
        }

        return [
            'summary' => 'The easiest way to become more noticeable on GitHub is to increase reviewable output, not just raw activity.',
            'actions' => array_slice($actions, 0, 4),
        ];
    }

    private function buildTrajectoryWindows(array $facts, Carbon $windowEnd): array
    {
        $windows = [
            'last_week' => ['label' => 'Last week', 'days' => 7],
            'last_month' => ['label' => 'Last month', 'days' => 30],
            'last_6_months' => ['label' => 'Last 6 months', 'days' => 182],
            'last_year' => ['label' => 'Last year', 'days' => 365],
        ];

        $result = [];

        foreach ($windows as $key => $window) {
            $start = $windowEnd->copy()->subDays($window['days'] - 1)->startOfDay();
            $windowFacts = $this->buildWindowFacts($facts, $start, $windowEnd);
            $windowMetrics = $this->scoreCalculator->calculate($windowFacts);
            $windowBuckets = $windowFacts['weekly_buckets'] ?? [];
            $result[$key] = [
                'label' => $window['label'],
                'days' => $window['days'],
                'score' => $windowMetrics['overall_score'],
                'confidence' => $windowMetrics['confidence'],
                'active_days' => $windowFacts['active_days'],
                'commits' => $windowFacts['commits_count'],
                'pull_requests' => $windowFacts['pull_requests_count'],
                'issues' => $windowFacts['issues_count'],
                'repos_touched' => $windowFacts['repos_touched'],
                'momentum' => $this->weeklyTimelineBuilder->momentumLabel($windowBuckets),
            ];
        }

        return $result;
    }

    private function buildSuggestedRepositories(array $facts, string $githubUsername, ?string $token): array
    {
        $languages = collect($facts['language_shares'] ?? [])->sortDesc()->keys()->take(2)->values()->all();
        $suggestions = collect();

        foreach ($languages as $language) {
            $query = sprintf('language:%s archived:false stars:>100', $language);
            try {
                $results = $this->github->searchRepositories($query, $token, 'updated', 'desc', 4);
            } catch (\Throwable) {
                continue;
            }

            foreach ($results as $repository) {
                $fullName = (string) ($repository['full_name'] ?? '');

                if ($fullName === '' || str_starts_with(strtolower($fullName), strtolower($githubUsername).'/')) {
                    continue;
                }

                $suggestions->push([
                    'repo' => $fullName,
                    'url' => $repository['html_url'] ?? null,
                    'language' => $repository['language'] ?? ucfirst($language),
                    'description' => $repository['description'] ?? null,
                    'stars' => (int) ($repository['stargazers_count'] ?? 0),
                    'why_fit' => sprintf('Matches the visible %s-heavy part of your current profile.', ucfirst((string) ($repository['language'] ?? $language))),
                    'realistic_contribution' => 'Start with a small issue, docs improvement, test, or bug fix that is easy to review.',
                ]);
            }
        }

        return $suggestions
            ->unique('repo')
            ->sortByDesc('stars')
            ->take(5)
            ->values()
            ->all();
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

    private function mergeThirtyDayPlanEnhancement(array $thirtyDayPlan, array $thirtyDayPlanNotes): array
    {
        $notesByIndex = collect($thirtyDayPlanNotes)->keyBy('index');

        return collect($thirtyDayPlan)->values()->map(function (array $item, int $index) use ($notesByIndex): array {
            $note = $notesByIndex->get($index);
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

    private function confidenceLabel(float $score): string
    {
        return $score >= 75 ? 'High' : ($score >= 45 ? 'Medium' : 'Low');
    }
}
