<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AnalysisRun */
class AnalysisRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'github_connection_id' => $this->github_connection_id,
            'github_username' => $this->github_username,
            'analysis_mode' => $this->analysis_mode,
            'status' => $this->status,
            'source_window_start' => $this->source_window_start?->toDateString(),
            'source_window_end' => $this->source_window_end?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'overall_score' => $this->overall_score,
            'confidence' => $this->confidence,
            'momentum_label' => $this->momentum_label,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'weekly_plan' => $this->weekly_plan,
            'context' => $this->context,
            'summary' => $this->summary,
            'evidence_summary' => $this->evidence_summary,
            'metric_snapshot' => $this->whenLoaded('metricSnapshot', fn () => AnalysisMetricSnapshotResource::make($this->metricSnapshot)),
            'weekly_buckets' => $this->whenLoaded('weeklyBuckets', fn () => AnalysisWeeklyBucketResource::collection($this->weeklyBuckets)),
            'skill_signals' => $this->whenLoaded('skillSignals', fn () => SkillSignalResource::collection($this->skillSignals)),
            'recommendations' => $this->whenLoaded('recommendations', fn () => AnalysisRecommendationResource::collection($this->recommendations)),
        ];
    }
}
