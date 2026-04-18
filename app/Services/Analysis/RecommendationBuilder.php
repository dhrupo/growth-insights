<?php

namespace App\Services\Analysis;

class RecommendationBuilder
{
    public function build(array $facts, array $metrics, array $skillSignals, string $momentumLabel): array
    {
        $weaknesses = $facts['weaknesses'] ?? [];
        $strengths = $facts['strengths'] ?? [];

        $recommendations = [];

        $recommendations[] = [
            'category' => 'focus',
            'title' => 'Protect consistency with a weekly shipping cadence',
            'body' => 'Block a fixed weekly window for one meaningful code contribution so the activity curve stays predictable.',
            'sort_order' => 1,
            'metadata' => [
                'why' => 'Consistency directly drives the growth score and stabilizes momentum.',
                'evidence' => $strengths[0] ?? null,
            ],
        ];

        $recommendations[] = [
            'category' => 'skill_growth',
            'title' => 'Increase your weakest signal with one targeted improvement',
            'body' => $this->weaknessDrivenRecommendation($weaknesses),
            'sort_order' => 2,
            'metadata' => [
                'weaknesses' => $weaknesses,
            ],
        ];

        $recommendations[] = [
            'category' => 'collaboration',
            'title' => 'Turn one weekly contribution into visible collaboration',
            'body' => $momentumLabel === 'declining'
                ? 'Recover momentum by opening a small reviewable PR or issue instead of waiting for a bigger change.'
                : 'Use one PR or issue each week to create stronger collaboration evidence and better cross-repo signals.',
            'sort_order' => 3,
            'metadata' => [
                'momentum_label' => $momentumLabel,
            ],
        ];

        return $recommendations;
    }

    public function weeklyPlan(array $facts, array $metrics, array $skillSignals, string $momentumLabel): array
    {
        $primaryWeakness = $facts['weaknesses'][0]['key'] ?? null;

        return [
            [
                'day' => 'Day 1',
                'title' => 'Pick one repo to improve',
                'action' => 'Choose a single repository or project area and define one visible improvement target.',
            ],
            [
                'day' => 'Day 2',
                'title' => 'Ship one focused change',
                'action' => 'Make a small but complete commit that moves the target forward.',
            ],
            [
                'day' => 'Day 3',
                'title' => 'Add validation',
                'action' => $primaryWeakness === 'testing'
                    ? 'Add one test path or validation check for the change you just shipped.'
                    : 'Add one guardrail, test, or check that makes the change safer to maintain.',
            ],
            [
                'day' => 'Day 4',
                'title' => 'Increase collaboration',
                'action' => 'Open a PR, review a change, or document a useful follow-up so the signal is visible to others.',
            ],
            [
                'day' => 'Day 5',
                'title' => 'Improve breadth',
                'action' => $primaryWeakness === 'diversity'
                    ? 'Touch a second repository, language, or tooling area to widen the signal.'
                    : 'Work in a secondary area such as docs, tests, or tooling to broaden the profile.',
            ],
            [
                'day' => 'Day 6',
                'title' => 'Document the result',
                'action' => 'Summarize what changed, what was learned, and what should happen next.',
            ],
            [
                'day' => 'Day 7',
                'title' => 'Review momentum',
                'action' => $momentumLabel === 'declining'
                    ? 'Check the weekly trend and reset the next week around a smaller, sustainable contribution.'
                    : 'Review the score movement and pick one follow-up action for the next week.',
            ],
        ];
    }

    private function weaknessDrivenRecommendation(array $weaknesses): string
    {
        if ($weaknesses === []) {
            return 'Keep the current trajectory and use the next contribution to reinforce the strongest growth pattern.';
        }

        $weaknessKey = $weaknesses[0]['key'] ?? 'consistency';

        return match ($weaknessKey) {
            'testing' => 'Add one test or validation path around the most recent change so the quality signal improves quickly.',
            'diversity' => 'Contribute to a second repo or introduce a second language/tooling area to widen the profile.',
            'collaboration' => 'Create one visible PR, issue, or review interaction to strengthen collaboration evidence.',
            'momentum' => 'Split the next week into two smaller contributions so the activity curve becomes steadier.',
            default => 'Create one repeatable weekly contribution pattern and keep it visible in a single repository.',
        };
    }
}
