<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AnalysisWeeklyBucket */
class AnalysisWeeklyBucketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'analysis_run_id' => $this->analysis_run_id,
            'week_start' => $this->week_start?->toDateString(),
            'week_end' => $this->week_end?->toDateString(),
            'active_days' => $this->active_days,
            'commits_count' => $this->commits_count,
            'pull_requests_count' => $this->pull_requests_count,
            'issues_count' => $this->issues_count,
            'reviews_count' => $this->reviews_count,
            'momentum_label' => $this->momentum_label,
            'trend_direction' => $this->trend_direction,
            'evidence' => $this->evidence,
        ];
    }
}
