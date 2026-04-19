<?php

namespace App\Services\Analysis;

class RecommendationBuilder
{
    public function build(array $facts, array $metrics, array $skillSignals, string $momentumLabel): array
    {
        $weaknesses = $facts['weaknesses'] ?? [];
        $strengths = $facts['strengths'] ?? [];
        $primaryWeakness = $weaknesses[0] ?? null;

        $recommendations = [];

        $recommendations[] = [
            'category' => 'focus',
            'title' => 'Protect consistency with a weekly shipping cadence',
            'body' => 'Block a fixed weekly window for one meaningful code contribution so the activity curve stays predictable.',
            'sort_order' => 1,
            'metadata' => [
                'why' => 'Consistency directly drives the growth score and stabilizes momentum.',
                'evidence' => $strengths[0] ?? null,
                'focus_area' => 'consistency',
                'success_metric' => 'Ship at least one visible contribution each week for four consecutive weeks.',
                'signal_gap' => $primaryWeakness['key'] ?? null,
            ],
        ];

        $recommendations[] = [
            'category' => 'skill_growth',
            'title' => 'Increase your weakest signal with one targeted improvement',
            'body' => $this->weaknessDrivenRecommendation($weaknesses),
            'sort_order' => 2,
            'metadata' => [
                'why' => 'The fastest way to improve the profile is to strengthen the weakest visible signal with one measurable output.',
                'weaknesses' => $weaknesses,
                'evidence' => $primaryWeakness,
                'focus_area' => $primaryWeakness['key'] ?? 'skill_growth',
                'success_metric' => $this->successMetricForWeakness($primaryWeakness['key'] ?? null),
                'signal_gap' => $primaryWeakness['key'] ?? null,
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
                'why' => 'Reviewable work gets noticed more quickly than isolated commit volume.',
                'momentum_label' => $momentumLabel,
                'evidence' => [
                    'pull_requests_count' => $facts['pull_requests_count'] ?? 0,
                    'issues_count' => $facts['issues_count'] ?? 0,
                    'repos_touched' => $facts['repos_touched'] ?? 0,
                ],
                'focus_area' => 'collaboration',
                'success_metric' => 'Create one public pull request or issue each week that another engineer can review.',
                'signal_gap' => 'oss_collaboration',
            ],
        ];

        $recommendations[] = [
            'category' => 'visibility',
            'title' => 'Make your work easier to notice and evaluate',
            'body' => $this->visibilityRecommendationBody($facts, $metrics, $momentumLabel),
            'sort_order' => 4,
            'metadata' => [
                'why' => 'Visibility improves when your work is public, reviewable, and easy to understand quickly.',
                'evidence' => [
                    'pull_requests_count' => $facts['pull_requests_count'] ?? 0,
                    'repos_touched' => $facts['repos_touched'] ?? 0,
                    'testing_score' => $facts['testing_score'] ?? 0,
                    'documentation_score' => $facts['documentation_score'] ?? 0,
                ],
                'focus_area' => 'visibility',
                'success_metric' => 'Create at least one contribution this month with a clear PR description, test or validation note, and a visible follow-up artifact.',
                'signal_gap' => 'visibility',
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

    public function monthlyPlan(array $facts, array $metrics, array $skillSignals, string $momentumLabel): array
    {
        $primaryWeakness = $facts['weaknesses'][0]['key'] ?? null;

        return [
            [
                'week' => 'Week 1',
                'title' => 'Stabilize your visible rhythm',
                'action' => 'Choose one repository or contribution lane and complete one reviewable improvement from start to finish.',
                'focus' => 'Consistency and visible shipping',
            ],
            [
                'week' => 'Week 2',
                'title' => 'Lift the weakest signal',
                'action' => match ($primaryWeakness) {
                    'testing' => 'Attach one test, validation step, or measurable quality check to public work.',
                    'diversity' => 'Touch a second repo, stack-adjacent tool, or project surface to widen your visible range.',
                    'collaboration' => 'Open one PR or issue that invites clear feedback from another engineer.',
                    default => 'Turn the weakest visible part of the profile into one concrete artifact.',
                },
                'focus' => ucfirst((string) ($primaryWeakness ?? 'signal')).' improvement',
            ],
            [
                'week' => 'Week 3',
                'title' => 'Increase reviewability',
                'action' => 'Publish one change with a strong PR description, clear scope, and a note on how it was validated.',
                'focus' => 'Visibility and collaboration',
            ],
            [
                'week' => 'Week 4',
                'title' => 'Create a public proof point',
                'action' => $momentumLabel === 'declining'
                    ? 'End the month with a smaller but fully visible artifact so the activity curve recovers cleanly.'
                    : 'End the month with a contribution that is easy to reference publicly in future work.',
                'focus' => 'Momentum and reputation',
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

    private function successMetricForWeakness(?string $weaknessKey): string
    {
        return match ($weaknessKey) {
            'testing' => 'Ship one visible change with a test or validation step attached.',
            'diversity' => 'Touch a second repository or adjacent tooling area this month.',
            'collaboration' => 'Create one reviewable PR or issue thread every week.',
            'momentum' => 'Sustain visible activity across at least three of the next four weeks.',
            default => 'Produce one public, reviewable artifact that strengthens the weakest signal.',
        };
    }

    private function visibilityRecommendationBody(array $facts, array $metrics, string $momentumLabel): string
    {
        if (($facts['pull_requests_count'] ?? 0) < 4) {
            return 'Create smaller pull requests with clearer titles and validation notes so your work becomes easier for others to notice and review.';
        }

        if (($facts['testing_score'] ?? 0) < 25 || ($facts['documentation_score'] ?? 0) < 25) {
            return 'Add tests, docs, or validation notes to visible work so the quality of each contribution is easier to trust quickly.';
        }

        if (($facts['repos_touched'] ?? 0) < 3) {
            return 'Spread a small part of your effort into an adjacent repository so your public work is easier to discover outside one codebase.';
        }

        return $momentumLabel === 'declining'
            ? 'Keep a smaller but steady stream of public artifacts so your visible signal does not disappear between larger pushes.'
            : 'Pair each visible contribution with a short explanation of what changed and how it was validated.';
    }
}
