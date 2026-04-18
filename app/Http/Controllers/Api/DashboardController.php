<?php

namespace App\Http\Controllers\Api;

use App\Models\AnalysisRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function summary(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        $snapshot = $run->metricSnapshot;
        $activity = $run->context['activity'] ?? [];

        return $this->json([
            'metrics' => [
                [
                    'key' => 'growth-score',
                    'title' => 'Growth score',
                    'value' => (string) round((float) $run->overall_score),
                    'delta' => ucfirst((string) $run->momentum_label),
                    'tone' => 'blue',
                    'description' => 'Weighted score across consistency, diversity, and contribution.',
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
                    'key' => 'testing-score',
                    'title' => 'Testing signal',
                    'value' => sprintf('%d', round((float) ($snapshot?->testing_score ?? 0))),
                    'delta' => sprintf('%d docs', round((float) ($snapshot?->documentation_score ?? 0))),
                    'tone' => 'rose',
                    'description' => 'Quality and workflow evidence inferred from test and automation signals.',
                    'icon' => 'Operation',
                ],
            ],
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        $buckets = $run->weeklyBuckets()->orderBy('week_start')->get();

        return $this->json([
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
        ]);
    }

    public function insights(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        $skills = $run->skillSignals()->orderByDesc('score')->get();
        $recommendations = $run->recommendations()->orderBy('sort_order')->get();

        return $this->json([
            'acquisitionMix' => [
                'categories' => $skills->pluck('skill_key')->map(fn (string $skill) => str($skill)->replace('_', ' ')->title()->toString())->all(),
                'values' => $skills->pluck('score')->map(fn ($score) => round((float) $score))->all(),
            ],
            'strengths' => collect($run->strengths ?? [])->values()->map(fn (array $item, int $index) => [
                'id' => $item['key'] ?? "strength-{$index}",
                'title' => $item['title'] ?? 'Strength',
                'detail' => $item['evidence'] ?? '',
                'impact' => sprintf('Score %.0f', (float) ($item['score'] ?? 0)),
                'priority' => 'high',
            ])->all(),
            'weaknesses' => collect($run->weaknesses ?? [])->values()->map(fn (array $item, int $index) => [
                'id' => $item['key'] ?? "weakness-{$index}",
                'title' => $item['title'] ?? 'Weakness',
                'detail' => $item['evidence'] ?? '',
                'impact' => sprintf('Score %.0f', (float) ($item['score'] ?? 0)),
                'priority' => 'medium',
            ])->all(),
            'recommendations' => $recommendations->map(fn ($item) => [
                'id' => (string) $item->id,
                'title' => $item->title,
                'detail' => $item->body,
                'impact' => ucfirst((string) ($item->metadata['confidence'] ?? 'Medium')),
                'priority' => $item->category,
            ])->all(),
        ]);
    }

    public function simulator(Request $request): JsonResponse
    {
        $run = $this->resolveRun($request);

        if ($run === null) {
            return $this->notFound('No analysis run found for the requested dashboard context.');
        }

        $snapshot = $run->metricSnapshot;
        $baseline = [
            'current_score' => (float) $run->overall_score,
            'current_consistency_score' => (float) $snapshot?->consistency_score,
            'current_diversity_score' => (float) $snapshot?->diversity_score,
            'current_contribution_score' => (float) $snapshot?->contribution_score,
            'extra_prs_per_week' => 1,
            'extra_commits_per_week' => 2,
            'extra_repos' => 1,
            'extra_languages' => 0,
            'extra_testing_signals' => 1,
            'extra_collaboration_signals' => 1,
        ];

        $current = (float) $baseline['current_score'];
        $projected = min(100, round(
            ((float) $baseline['current_consistency_score'] + 2.4) * 0.4
            + ((float) $baseline['current_diversity_score'] + 6.5) * 0.3
            + ((float) $baseline['current_contribution_score'] + 6.1) * 0.3,
            2,
        ));

        return $this->json([
            'current' => round($current, 1).'/100',
            'projected' => round($projected, 1).'/100',
            'uplift' => sprintf('%+.1f', round($projected - $current, 1)),
            'confidence' => $this->confidenceLabel((float) $run->confidence).' confidence',
            'notes' => [
                'Projection assumes one extra PR per week and one additional testing signal.',
                'Diversity grows when work touches one extra repository or stack-adjacent area.',
                'Use the dedicated simulation endpoint for custom scenario planning.',
            ],
            'series' => [
                ['label' => 'Current', 'value' => round($current, 1)],
                ['label' => 'Consistency+', 'value' => round(min(100, $current + 2.4), 1)],
                ['label' => 'Contribution+', 'value' => round(min(100, $current + 4.2), 1)],
                ['label' => 'Projected', 'value' => round($projected, 1)],
            ],
        ]);
    }

    private function resolveRun(Request $request): ?AnalysisRun
    {
        $query = AnalysisRun::query()
            ->where('status', 'completed')
            ->with(['metricSnapshot', 'weeklyBuckets', 'skillSignals', 'recommendations']);

        if ($request->filled('analysis_run_id')) {
            return $query->find($request->integer('analysis_run_id'));
        }

        if ($request->filled('github_username')) {
            $query->where('github_username', $request->string('github_username')->toString());
        }

        return $query->latest('completed_at')->latest('id')->first();
    }

    private function confidenceLabel(float $score): string
    {
        return $score >= 75 ? 'High' : ($score >= 45 ? 'Medium' : 'Low');
    }
}
