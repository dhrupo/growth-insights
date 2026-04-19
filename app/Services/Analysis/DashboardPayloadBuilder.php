<?php

namespace App\Services\Analysis;

use App\Models\AnalysisRun;
use App\Models\GitHubConnection;
use Illuminate\Support\Collection;

class DashboardPayloadBuilder
{
    private const SCORE_SCALE = 10;

    public function connectionState(?GitHubConnection $connection, ?string $githubUsername = null): array
    {
        $username = $connection?->github_username ?: $githubUsername;

        if ($connection === null) {
            return [
                'enabled' => false,
                'connected' => false,
                'workspace' => 'GitHub is not connected',
                'note' => 'Connect GitHub to analyze your own contribution history and optionally include private repositories you authorize.',
            ];
        }

        $connected = filled($connection->access_token);
        $syncStatus = $connection->sync_status ?: 'idle';

        return [
            'enabled' => true,
            'connected' => $connected,
            'workspace' => $connected
                ? sprintf('Connected as %s', $username ?: 'your GitHub account')
                : 'GitHub connection needs attention',
            'note' => match (true) {
                $syncStatus === 'failed' && filled($connection->sync_error) => sprintf('The last sync failed: %s', $connection->sync_error),
                $connection->last_synced_at !== null => sprintf('Last synced %s.', $connection->last_synced_at->diffForHumans()),
                default => 'Connect GitHub and run an analysis to build your profile from the account you authorized.',
            },
        ];
    }

    public function summary(AnalysisRun $run): array
    {
        $snapshot = $run->metricSnapshot;
        $activity = $run->context['activity'] ?? [];

        return [
            'metrics' => [
                [
                    'key' => 'growth-score',
                    'title' => 'Overall score',
                    'value' => $this->formatScore((float) $run->overall_score).'/10',
                    'delta' => ucfirst((string) $run->momentum_label),
                    'tone' => 'blue',
                    'description' => 'A combined score based on consistency, range of work, and visible contributions.',
                    'icon' => 'DataLine',
                ],
                [
                    'key' => 'active-days',
                    'title' => 'Active days',
                    'value' => (string) ($activity['active_days'] ?? 0),
                    'delta' => sprintf('Report certainty: %s', $this->confidenceLabel((float) $run->confidence)),
                    'tone' => 'emerald',
                    'description' => 'How many days had visible coding, pull request, or issue activity.',
                    'icon' => 'TrendCharts',
                ],
                [
                    'key' => 'pull-requests',
                    'title' => 'Visible pull requests',
                    'value' => (string) ($activity['pull_requests_count'] ?? 0),
                    'delta' => sprintf('%d issues', (int) ($activity['issues_count'] ?? 0)),
                    'tone' => 'amber',
                    'description' => 'A quick sign of how much of your work is visible and reviewable by others.',
                    'icon' => 'Collection',
                ],
                [
                    'key' => 'testing-signal',
                    'title' => 'Testing and quality',
                    'value' => (string) round((float) ($snapshot?->testing_score ?? 0)),
                    'delta' => sprintf('%d docs', round((float) ($snapshot?->documentation_score ?? 0))),
                    'tone' => 'rose',
                    'description' => 'A rough read on visible testing, checks, and quality-related work.',
                    'icon' => 'Operation',
                ],
            ],
        ];
    }

    public function timeline(AnalysisRun $run): array
    {
        $buckets = $run->weeklyBuckets()->orderBy('week_start')->get();

        return [
            'categories' => $buckets->map(fn ($bucket) => $bucket->week_start->format('M d'))->all(),
            'series' => [
                [
                    'name' => 'Commits',
                    'type' => 'line',
                    'data' => $buckets->pluck('commits_count')->all(),
                ],
                [
                    'name' => 'Pull requests',
                    'type' => 'line',
                    'data' => $buckets->pluck('pull_requests_count')->all(),
                ],
            ],
            'updatedAt' => optional($run->completed_at)->diffForHumans(),
        ];
    }

    public function insights(AnalysisRun $run): array
    {
        $skills = $run->skillSignals()->orderByDesc('score')->get();
        $recommendations = $run->recommendations()->orderBy('sort_order')->get();

        return [
            'acquisitionMix' => [
                'categories' => $skills->pluck('skill_key')->map(fn (string $skill) => str($skill)->replace('_', ' ')->title()->toString())->all(),
                'values' => $skills->pluck('score')->map(fn ($score) => round((float) $score))->all(),
            ],
            'strengths' => $this->mapInsights($run->strengths ?? []),
            'weaknesses' => $this->mapInsights($run->weaknesses ?? []),
            'recommendations' => $recommendations->map(fn ($item) => [
                'id' => (string) $item->id,
                'title' => $item->title,
                'detail' => $item->body,
                'impact' => ucfirst((string) ($item->metadata['confidence'] ?? 'Medium')),
                'priority' => $item->category,
            ])->all(),
        ];
    }

    public function simulator(AnalysisRun $run): array
    {
        $snapshot = $run->metricSnapshot;
        $current = (float) $run->overall_score;
        $projected = min(100, round(
            ((float) ($snapshot?->consistency_score ?? 0) + 2.4) * 0.4
            + ((float) ($snapshot?->diversity_score ?? 0) + 6.5) * 0.3
            + ((float) ($snapshot?->contribution_score ?? 0) + 6.1) * 0.3,
            2,
        ));

        return [
            'current' => $this->formatScore($current).'/10',
            'projected' => $this->formatScore($projected).'/10',
            'uplift' => sprintf('%+.1f', round(($projected - $current) / self::SCORE_SCALE, 1)),
            'confidence' => $this->confidenceLabel((float) $run->confidence).' confidence',
            'notes' => [
                'Projection assumes one extra PR per week and one additional testing signal.',
                'Diversity grows when work touches one extra repository or stack-adjacent area.',
                'Use the dedicated simulation endpoint for custom scenario planning.',
            ],
            'series' => [
                ['label' => 'Current', 'value' => (float) $this->formatScore($current)],
                ['label' => 'Consistency+', 'value' => (float) $this->formatScore(min(100, $current + 2.4))],
                ['label' => 'Contribution+', 'value' => (float) $this->formatScore(min(100, $current + 4.2))],
                ['label' => 'Projected', 'value' => (float) $this->formatScore($projected)],
            ],
        ];
    }

    public function workbench(AnalysisRun $run, ?GitHubConnection $connection = null): array
    {
        $profile = $run->context['profile'] ?? [];
        $activity = $run->context['activity'] ?? [];
        $context = $run->context ?? [];
        $enhancement = $run->ai_enhancement ?? [];
        $scoreBreakdownRaw = $run->metricSnapshot?->score_breakdown ?? [];
        $scoreBreakdown = collect($scoreBreakdownRaw);
        $mergedRecommendations = collect($enhancement['merged_recommendations'] ?? []);
        $mergedWeeklyPlan = collect($enhancement['merged_weekly_plan'] ?? []);
        $mergedThirtyDayPlan = collect($enhancement['merged_thirty_day_plan'] ?? []);
        $howToGetNoticed = $this->howToGetNoticedPayload($enhancement, $context, $run);
        $improvementActions = $this->improvementActionsPayload($enhancement, $run);
        $trajectory = $this->trajectoryPayload($enhancement, $context);

        if ($scoreBreakdown->isNotEmpty() && ! is_array($scoreBreakdown->first())) {
            $scoreBreakdown = $scoreBreakdown->map(
                fn ($value, $label) => ['label' => str((string) $label)->replace('_', ' ')->title()->toString(), 'value' => $value]
            )->values();
        }

        return [
            'analysisRunId' => $run->id,
            'profile' => [
                'username' => $run->github_username,
                'displayName' => $profile['name'] ?: str($run->github_username)->replace(['-', '_'], ' ')->title()->toString(),
                'role' => $this->topSkillLabel($run->skillSignals),
                'bio' => $run->summary,
                'followers' => (int) ($profile['followers'] ?? 0),
                'publicRepos' => (int) ($profile['public_repos'] ?? 0),
                'contributionStreak' => (int) ($activity['active_weeks'] ?? 0),
                'publicPullRequests' => (int) ($activity['pull_requests_count'] ?? 0),
            ],
            'summary' => array_values(array_filter([
                $run->summary,
                $enhancement['summary'] ?? null,
            ])),
            'evidenceSummary' => $run->evidence_summary,
            'weeklyPlan' => collect($run->weekly_plan ?? [])->map(function (array $item) use ($mergedWeeklyPlan): array {
                $notes = $mergedWeeklyPlan;
                $aiNote = $notes->firstWhere('day', $item['day'])['ai_note']
                    ?? $notes->firstWhere('title', $item['title'])['ai_note']
                    ?? null;

                return [
                    'day' => $item['day'] ?? null,
                    'title' => $item['title'] ?? null,
                    'action' => $item['action'] ?? null,
                    'aiNote' => $aiNote,
                ];
            })->values()->all(),
            'thirtyDayPlan' => collect($context['thirty_day_plan'] ?? [])->values()->map(function (array $item, int $index) use ($mergedThirtyDayPlan): array {
                return [
                    'week' => $item['week'] ?? "Week ".($index + 1),
                    'title' => $item['title'] ?? 'Plan item',
                    'action' => $item['action'] ?? null,
                    'focus' => $item['focus'] ?? null,
                    'aiNote' => $mergedThirtyDayPlan->get($index)['ai_note'] ?? null,
                ];
            })->all(),
            'connection' => [
                ...$this->connectionState($connection, $run->github_username),
            ],
            'scoreBreakdown' => [
                'categories' => $scoreBreakdown->pluck('label')->all(),
                'values' => $scoreBreakdown->pluck('value')->map(fn ($value) => (float) $this->formatScore((float) $value))->all(),
                'benchmark' => $scoreBreakdown->map(fn (array $item) => (float) $this->formatScore((float) $item['value'] * 0.85))->all(),
            ],
            'skillDistribution' => [
                'categories' => $run->skillSignals->pluck('skill_key')->map(fn ($skill) => str($skill)->replace('_', ' ')->title()->toString())->all(),
                'values' => $run->skillSignals->pluck('score')->map(fn ($value) => round((float) $value))->all(),
            ],
            'skillSignals' => $run->skillSignals->sortByDesc('score')->values()->map(function ($signal): array {
                $evidence = $signal->evidence ?? [];

                return [
                    'key' => $signal->skill_key,
                    'label' => str($signal->skill_key)->replace('_', ' ')->title()->toString(),
                    'score' => (float) $this->formatScore((float) $signal->score),
                    'confidence' => $this->confidenceLabel((float) $signal->confidence),
                    'rawConfidence' => round((float) $signal->confidence),
                    'notes' => $signal->notes,
                    'evidence' => $this->skillEvidenceBullets($evidence),
                ];
            })->all(),
            'strengths' => $this->mapInsights($run->strengths ?? []),
            'weaknesses' => $this->mapInsights($run->weaknesses ?? []),
            'recommendations' => $run->recommendations->sortBy('sort_order')->values()->map(function ($item, int $index) use ($mergedRecommendations): array {
                $merged = $mergedRecommendations->get($index) ?? [];

                return [
                'id' => (string) $item->id,
                'title' => $item->title,
                'detail' => $item->body,
                'impact' => ucfirst((string) ($merged['ai_confidence'] ?? $item->metadata['confidence'] ?? 'Medium')),
                'priority' => $item->category,
                'why' => $item->metadata['why'] ?? null,
                'evidence' => $item->metadata['evidence'] ?? null,
                'successMetric' => $item->metadata['success_metric'] ?? null,
                'focusArea' => $item->metadata['focus_area'] ?? null,
                'aiNote' => $merged['ai_note'] ?? null,
            ];
            })->all(),
            'improvementActions' => $improvementActions,
            'howToGetNoticed' => $howToGetNoticed,
            'trajectory' => $trajectory,
            'contributionStyle' => [
                'label' => $context['contribution_style']['label'] ?? 'Builder',
                'summary' => $context['contribution_style']['summary'] ?? '',
                'confidence' => $context['contribution_style']['confidence'] ?? 'Medium',
                'evidence' => $context['contribution_style']['evidence'] ?? [],
            ],
            'credibilityNotice' => $context['credibility_notice'] ?? null,
            'analyzedRepositories' => collect($context['repositories'] ?? [])->values()->map(function (array $repo): array {
                return [
                    'name' => $repo['name'] ?? $repo['full_name'] ?? 'Repository',
                    'fullName' => $repo['full_name'] ?? null,
                    'url' => $repo['html_url'] ?? null,
                    'visibility' => $repo['visibility'] ?? 'public',
                    'language' => $repo['language'] ?? 'Unknown',
                    'lastActivity' => $repo['last_activity'] ?? $repo['updated_at'] ?? null,
                    'commitCount' => (int) ($repo['commit_count'] ?? 0),
                    'pullRequestCount' => (int) ($repo['pull_request_count'] ?? 0),
                    'issueCount' => (int) ($repo['issue_count'] ?? 0),
                    'description' => $repo['description'] ?? null,
                    'signals' => $repo['signals'] ?? [],
                ];
            })->all(),
            'suggestedRepositories' => array_values(array_filter(array_map(function (array $item): ?array {
                if (($item['repo'] ?? '') === '') {
                    return null;
                }

                return [
                    'repo' => $item['repo'],
                    'url' => $item['url'] ?? null,
                    'language' => $item['language'] ?? null,
                    'description' => $item['description'] ?? null,
                    'whyFit' => $item['why_fit'] ?? null,
                    'realisticContribution' => $item['realistic_contribution'] ?? null,
                    'stars' => isset($item['stars']) ? (int) $item['stars'] : null,
                ];
            }, ($enhancement['suggested_repositories'] ?: ($context['suggested_repositories'] ?? []))))),
            'source' => $run->analysis_mode === 'public_private' ? 'mixed' : 'live',
            'lastAnalyzedAt' => optional($run->completed_at)->toIso8601String(),
        ];
    }

    public function currentSession(?GitHubConnection $connection, ?AnalysisRun $run = null): array
    {
        if ($run !== null) {
            return $this->workbench($run, $connection ?? $run->githubConnection);
        }

        $username = $connection?->github_username ?? '';

        return [
            'analysisRunId' => null,
            'profile' => [
                'username' => $username,
                'displayName' => $username !== ''
                    ? str($username)->replace(['-', '_'], ' ')->title()->toString()
                    : 'Your GitHub profile',
                'role' => '',
                'bio' => $connection !== null
                    ? 'GitHub is connected. Run an analysis to turn your activity into an easy-to-read profile.'
                    : 'Connect GitHub to turn your activity into an easy-to-read profile.',
                'followers' => 0,
                'publicRepos' => 0,
                'contributionStreak' => 0,
                'publicPullRequests' => 0,
            ],
            'summary' => [],
            'evidenceSummary' => null,
            'weeklyPlan' => [],
            'thirtyDayPlan' => [],
            'connection' => $this->connectionState($connection, $username),
            'scoreBreakdown' => [
                'categories' => [],
                'values' => [],
                'benchmark' => [],
            ],
            'skillDistribution' => [
                'categories' => [],
                'values' => [],
            ],
            'skillSignals' => [],
            'strengths' => [],
            'weaknesses' => [],
            'recommendations' => [],
            'improvementActions' => [],
            'howToGetNoticed' => [
                'summary' => '',
                'actions' => [],
            ],
            'trajectory' => [
                'windows' => [],
                'summary' => '',
                'outlook' => '',
                'confidence' => 'Low',
            ],
            'contributionStyle' => [
                'label' => '',
                'summary' => '',
                'confidence' => '',
                'evidence' => [],
            ],
            'credibilityNotice' => null,
            'analyzedRepositories' => [],
            'suggestedRepositories' => [],
            'source' => 'empty',
            'lastAnalyzedAt' => null,
        ];
    }

    private function mapInsights(array $items): array
    {
        return collect($items)->values()->map(fn (array $item, int $index) => [
            'id' => $item['key'] ?? "insight-{$index}",
            'title' => $item['title'] ?? 'Insight',
            'detail' => $item['evidence'] ?? '',
            'impact' => $this->scoreImpactLabel((float) ($item['score'] ?? 0)),
            'priority' => ((float) ($item['score'] ?? 0)) >= 70 ? 'high' : 'medium',
        ])->all();
    }

    private function topSkillLabel(Collection $signals): string
    {
        $top = $signals->sortByDesc('score')->first();

        if (! $top) {
            return 'Developer';
        }

        return str($top->skill_key)->replace('_', ' ')->title()->append(' focus')->toString();
    }

    private function confidenceLabel(float $score): string
    {
        return $score >= 75 ? 'High' : ($score >= 45 ? 'Medium' : 'Low');
    }

    private function formatScore(float $value): string
    {
        return number_format(max(0, min(self::SCORE_SCALE, $value / self::SCORE_SCALE)), 1);
    }

    private function scoreImpactLabel(float $score): string
    {
        return $this->formatScore($score).'/10';
    }

    private function skillEvidenceBullets(array $evidence): array
    {
        $bullets = [];

        if (isset($evidence['language_share'])) {
            $bullets[] = sprintf('Language share: %.1f%%', ((float) $evidence['language_share']) * 100);
        }

        if (isset($evidence['backend_share'])) {
            $bullets[] = sprintf('Backend share: %.1f%%', ((float) $evidence['backend_share']) * 100);
        }

        if (isset($evidence['frontend_share'])) {
            $bullets[] = sprintf('Frontend share: %.1f%%', ((float) $evidence['frontend_share']) * 100);
        }

        if (isset($evidence['keyword_hits'])) {
            $bullets[] = sprintf('Keyword hits: %d', (int) $evidence['keyword_hits']);
        }

        if (isset($evidence['pull_requests_count'])) {
            $bullets[] = sprintf('PRs: %d', (int) $evidence['pull_requests_count']);
        }

        if (isset($evidence['issues_count'])) {
            $bullets[] = sprintf('Issues: %d', (int) $evidence['issues_count']);
        }

        if (isset($evidence['repos_touched'])) {
            $bullets[] = sprintf('Repositories touched: %d', (int) $evidence['repos_touched']);
        }

        return array_values($bullets);
    }

    private function howToGetNoticedPayload(array $enhancement, array $context, AnalysisRun $run): array
    {
        $actions = array_values(array_filter(array_map(function (array $item): ?array {
            if (($item['action'] ?? '') === '') {
                return null;
            }

            return [
                'action' => $item['action'] ?? '',
                'why' => $item['why'] ?? '',
                'evidence' => $item['evidence'] ?? '',
            ];
        }, ($enhancement['how_to_get_noticed']['actions'] ?? []))));

        if ($actions === []) {
            $actions = array_values(array_filter(array_map(function (array $item): ?array {
                if (($item['action'] ?? '') === '') {
                    return null;
                }

                return [
                    'action' => $item['action'] ?? '',
                    'why' => $item['why'] ?? '',
                    'evidence' => $item['evidence'] ?? '',
                ];
            }, ($context['visibility_advice']['actions'] ?? []))));
        }

        if ($actions === []) {
            $actions = $run->recommendations->sortBy('sort_order')->take(3)->values()->map(function ($item): array {
                return [
                    'action' => $item->title,
                    'why' => $item->metadata['why'] ?? $item->body,
                    'evidence' => $item->metadata['success_metric'] ?? '',
                ];
            })->all();
        }

        return [
            'summary' => $enhancement['how_to_get_noticed']['summary']
                ?? ($context['visibility_advice']['summary'] ?? 'Increase reviewable output and visible collaboration to improve discoverability.'),
            'actions' => $actions,
        ];
    }

    private function improvementActionsPayload(array $enhancement, AnalysisRun $run): array
    {
        $actions = array_values(array_filter(array_map(function (array $item): ?array {
            if (($item['title'] ?? '') === '' && ($item['detail'] ?? '') === '') {
                return null;
            }

            return [
                'title' => $item['title'] ?? '',
                'detail' => $item['detail'] ?? '',
                'why' => $item['why'] ?? '',
                'metric' => $item['metric'] ?? '',
            ];
        }, $enhancement['improvement_actions'] ?? [])));

        if ($actions !== []) {
            return $actions;
        }

        return $run->recommendations->sortBy('sort_order')->take(3)->values()->map(function ($item): array {
            return [
                'title' => $item->title,
                'detail' => $item->body,
                'why' => $item->metadata['why'] ?? '',
                'metric' => $item->metadata['success_metric'] ?? '',
            ];
        })->all();
    }

    private function trajectoryPayload(array $enhancement, array $context): array
    {
        $windows = $context['trajectory'] ?? [];
        $windowCollection = collect(is_array($windows) ? $windows : []);
        $averageConfidence = (float) $windowCollection->avg('confidence');
        $averageScore = (float) $windowCollection->avg('score');
        $latestMomentum = (string) ($windowCollection->last()['momentum'] ?? 'stable');
        $enhancedConfidence = (string) ($enhancement['trajectory_12_months']['confidence'] ?? '');
        $hasEnhancedTrajectory = filled($enhancement['trajectory_12_months']['summary'] ?? null)
            || filled($enhancement['trajectory_12_months']['outlook'] ?? null);

        return [
            'windows' => $windows,
            'summary' => $enhancement['trajectory_12_months']['summary']
                ?? sprintf('Visible signal is %s with an average observed score of %s/10 across the tracked windows.', $latestMomentum, $this->formatScore($averageScore)),
            'outlook' => $enhancement['trajectory_12_months']['outlook']
                ?? 'If the current pace holds, the strongest gains should come from keeping visible output steady and improving reviewable collaboration artifacts.',
            'confidence' => ucfirst($hasEnhancedTrajectory && $enhancedConfidence !== ''
                ? $enhancedConfidence
                : $this->confidenceLabel($averageConfidence)),
        ];
    }

}
