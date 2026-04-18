<?php

namespace App\Services\Analysis;

class ScoreSimulationService
{
    public function simulate(array $input): array
    {
        $current = [
            'consistency' => (float) $input['current_consistency_score'],
            'diversity' => (float) $input['current_diversity_score'],
            'contribution' => (float) $input['current_contribution_score'],
        ];

        $projected = [
            'consistency' => $this->clamp($current['consistency'] + ((float) ($input['extra_commits_per_week'] ?? 0) * 1.2)),
            'diversity' => $this->clamp($current['diversity'] + ((float) ($input['extra_repos'] ?? 0) * 6.5) + ((float) ($input['extra_languages'] ?? 0) * 7.5)),
            'contribution' => $this->clamp($current['contribution'] + ((float) ($input['extra_prs_per_week'] ?? 0) * 3.5) + ((float) ($input['extra_commits_per_week'] ?? 0) * 1.1) + ((float) ($input['extra_testing_signals'] ?? 0) * 3.0) + ((float) ($input['extra_collaboration_signals'] ?? 0) * 2.0)),
        ];

        $currentOverall = (float) $input['current_score'];
        $projectedOverall = $this->clamp(($projected['consistency'] * 0.4) + ($projected['diversity'] * 0.3) + ($projected['contribution'] * 0.3));

        return [
            'current_score' => round($currentOverall, 2),
            'projected_score' => round($projectedOverall, 2),
            'delta' => round($projectedOverall - $currentOverall, 2),
            'current_components' => array_map(static fn (float $value) => round($value, 2), $current),
            'projected_components' => array_map(static fn (float $value) => round($value, 2), $projected),
            'affected_dimensions' => array_values(array_filter([
                ($input['extra_commits_per_week'] ?? 0) > 0 ? 'consistency' : null,
                (($input['extra_repos'] ?? 0) > 0 || ($input['extra_languages'] ?? 0) > 0) ? 'diversity' : null,
                (($input['extra_prs_per_week'] ?? 0) > 0 || ($input['extra_testing_signals'] ?? 0) > 0 || ($input['extra_collaboration_signals'] ?? 0) > 0) ? 'contribution' : null,
            ])),
        ];
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
