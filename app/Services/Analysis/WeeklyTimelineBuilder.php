<?php

namespace App\Services\Analysis;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class WeeklyTimelineBuilder
{
    public function build(array $facts): array
    {
        $start = Carbon::parse($facts['window_start']);
        $end = Carbon::parse($facts['window_end']);
        $events = collect($facts['events'] ?? []);

        $buckets = [];

        foreach (CarbonPeriod::create($start->copy()->startOfWeek(), '1 week', $end->copy()->endOfWeek()) as $weekStart) {
            $weekEnd = $weekStart->copy()->endOfWeek();
            $weekEvents = $events->filter(static function (array $event) use ($weekStart, $weekEnd): bool {
                $date = Carbon::parse($event['date']);

                return $date->betweenIncluded($weekStart, $weekEnd);
            });

            $commits = $weekEvents->where('type', 'commit')->count();
            $pullRequests = $weekEvents->where('type', 'pull_request')->count();
            $issues = $weekEvents->where('type', 'issue')->count();
            $activeDays = $weekEvents->pluck('date')->map(static fn (string $date) => Carbon::parse($date)->toDateString())->unique()->count();
            $total = $commits + $pullRequests + $issues;

            $buckets[] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'active_days' => $activeDays,
                'commits_count' => $commits,
                'pull_requests_count' => $pullRequests,
                'issues_count' => $issues,
                'reviews_count' => 0,
                'momentum_label' => $this->labelForWeek($total),
                'trend_direction' => $this->trendForWeek($total, (int) ($buckets[array_key_last($buckets)]['total_events'] ?? 0)),
                'total_events' => $total,
                'evidence' => [
                    'active_days' => $activeDays,
                    'events' => $total,
                ],
            ];
        }

        return $buckets;
    }

    public function momentumLabel(array $weeklyBuckets): string
    {
        if ($weeklyBuckets === []) {
            return 'quiet';
        }

        $total = collect($weeklyBuckets)->sum('total_events');
        $recent = collect($weeklyBuckets)->slice(max(0, count($weeklyBuckets) - 2))->sum('total_events');
        $previous = collect($weeklyBuckets)->slice(max(0, count($weeklyBuckets) - 4), 2)->sum('total_events');

        if ($recent === 0 && $total === 0) {
            return 'quiet';
        }

        if ($recent >= max(4, (int) round($previous * 1.2))) {
            return 'growing';
        }

        if ($recent <= (int) max(1, round($previous * 0.75))) {
            return 'declining';
        }

        return 'stable';
    }

    private function labelForWeek(int $total): string
    {
        return match (true) {
            $total >= 8 => 'strong',
            $total >= 4 => 'stable',
            $total >= 1 => 'light',
            default => 'quiet',
        };
    }

    private function trendForWeek(int $total, int $previousTotal): string
    {
        return match (true) {
            $total > max(0, (int) round($previousTotal * 1.2)) => 'up',
            $total < max(1, (int) round($previousTotal * 0.8)) => 'down',
            default => 'flat',
        };
    }
}
