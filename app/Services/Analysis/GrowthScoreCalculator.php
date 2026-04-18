<?php

namespace App\Services\Analysis;

class GrowthScoreCalculator
{
    public function calculate(array $facts): array
    {
        $windowDays = max(1, (int) ($facts['window_days'] ?? 56));
        $totalWeeks = max(1, (int) ($facts['total_weeks'] ?? 8));
        $activeWeeks = max(0, (int) ($facts['active_weeks'] ?? 0));
        $activeDays = max(0, (int) ($facts['active_days'] ?? 0));
        $commits = max(0, (int) ($facts['commits_count'] ?? 0));
        $pullRequests = max(0, (int) ($facts['pull_requests_count'] ?? 0));
        $issues = max(0, (int) ($facts['issues_count'] ?? 0));
        $repoCount = max(0, (int) ($facts['repos_touched'] ?? 0));
        $languageEntropy = (float) ($facts['language_entropy'] ?? 0.0);
        $languageBreadth = (float) ($facts['language_breadth'] ?? 0.0);

        $consistency = $this->clamp(
            (0.7 * ($activeWeeks / $totalWeeks) * 100) + (0.3 * ($activeDays / $windowDays) * 100)
        );

        $diversity = $this->clamp((0.6 * $languageEntropy) + (0.4 * $languageBreadth));

        $commitScore = $this->boundedRatio($commits, $totalWeeks * 3);
        $prScore = $this->boundedRatio($pullRequests, $totalWeeks);
        $issueScore = $this->boundedRatio($issues, max(1, (int) round($totalWeeks * 0.7)));
        $breadthBonus = $this->boundedRatio($repoCount, $totalWeeks);
        $contribution = $this->clamp((0.42 * $commitScore) + (0.34 * $prScore) + (0.16 * $issueScore) + (0.08 * $breadthBonus));

        $overall = $this->clamp((0.4 * $consistency) + (0.3 * $diversity) + (0.3 * $contribution));

        $confidence = $this->clamp(
            28
            + min(28, $commits * 1.1 + $pullRequests * 2.0 + $issues * 1.0)
            + min(16, $activeWeeks * 2.0)
            + min(18, $repoCount * 1.5)
        );

        return [
            'consistency_score' => round($consistency, 2),
            'diversity_score' => round($diversity, 2),
            'contribution_score' => round($contribution, 2),
            'overall_score' => round($overall, 2),
            'confidence' => round($confidence, 2),
            'score_breakdown' => [
                'consistency' => round(0.4 * $consistency, 2),
                'diversity' => round(0.3 * $diversity, 2),
                'contribution' => round(0.3 * $contribution, 2),
            ],
        ];
    }

    private function boundedRatio(int|float $value, int|float $target): float
    {
        if ($target <= 0) {
            return 0.0;
        }

        return $this->clamp(($value / $target) * 100);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
