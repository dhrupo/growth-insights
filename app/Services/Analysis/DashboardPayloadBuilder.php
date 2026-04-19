<?php

namespace App\Services\Analysis;

use App\Models\AnalysisRun;
use App\Models\GitHubConnection;
use Illuminate\Support\Collection;

class DashboardPayloadBuilder
{
    private const SCORE_SCALE = 10;

    public function summary(AnalysisRun $run): array
    {
        $snapshot = $run->metricSnapshot;
        $activity = $run->context['activity'] ?? [];

        return [
            'metrics' => [
                [
                    'key' => 'growth-score',
                    'title' => 'Growth score',
                    'value' => $this->formatScore((float) $run->overall_score).'/10',
                    'delta' => ucfirst((string) $run->momentum_label),
                    'tone' => 'blue',
                    'description' => 'Weighted score across consistency, diversity, and contribution on a 10-point scale.',
                    'icon' => 'DataLine',
                ],
                [
                    'key' => 'active-days',
                    'title' => 'Active days',
                    'value' => (string) ($activity['active_days'] ?? 0),
                    'delta' => sprintf('%s confidence', $this->confidenceLabel((float) $run->confidence)),
                    'tone' => 'emerald',
                    'description' => 'Days with visible coding, PR, or issue activity inside the analysis window.',
                    'icon' => 'TrendCharts',
                ],
                [
                    'key' => 'pull-requests',
                    'title' => 'Pull requests',
                    'value' => (string) ($activity['pull_requests_count'] ?? 0),
                    'delta' => sprintf('%d issues', (int) ($activity['issues_count'] ?? 0)),
                    'tone' => 'amber',
                    'description' => 'Visible collaboration output across the sampled repositories.',
                    'icon' => 'Collection',
                ],
                [
                    'key' => 'testing-signal',
                    'title' => 'Testing signal',
                    'value' => (string) round((float) ($snapshot?->testing_score ?? 0)),
                    'delta' => sprintf('%d docs', round((float) ($snapshot?->documentation_score ?? 0))),
                    'tone' => 'rose',
                    'description' => 'Quality and workflow evidence inferred from tests and automation.',
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
        $scoreBreakdownRaw = $run->metricSnapshot?->score_breakdown ?? [];
        $scoreBreakdown = collect($scoreBreakdownRaw);

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
                $run->ai_enhancement['summary'] ?? null,
            ])),
            'evidenceSummary' => $run->evidence_summary,
            'weeklyPlan' => collect($run->weekly_plan ?? [])->map(function (array $item) use ($run): array {
                $notes = collect($run->ai_enhancement['merged_weekly_plan'] ?? []);
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
            'connection' => [
                'enabled' => $connection !== null,
                'connected' => $connection !== null && filled($connection->access_token),
                'workspace' => $connection !== null
                    ? sprintf('GitHub is connected for %s', $run->github_username)
                    : 'GitHub is not connected',
                'note' => $connection !== null && filled($connection->access_token)
                    ? 'Private repositories are included in the most recent synced analysis.'
                    : 'Connect GitHub to include any repositories you authorize beyond the public baseline.',
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
            'strengths' => $this->mapInsights($run->strengths ?? []),
            'weaknesses' => $this->mapInsights($run->weaknesses ?? []),
            'recommendations' => $run->recommendations->sortBy('sort_order')->map(fn ($item) => [
                'id' => (string) $item->id,
                'title' => $item->title,
                'detail' => $item->body,
                'impact' => ucfirst((string) ($item->metadata['confidence'] ?? 'Medium')),
                'priority' => $item->category,
                'why' => $item->metadata['why'] ?? null,
                'evidence' => $item->metadata['evidence'] ?? null,
            ])->values()->all(),
            'source' => $run->analysis_mode === 'public_private' ? 'mixed' : 'live',
            'lastAnalyzedAt' => optional($run->completed_at)->toIso8601String(),
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

}
