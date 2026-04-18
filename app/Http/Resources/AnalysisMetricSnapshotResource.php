<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AnalysisMetricSnapshot */
class AnalysisMetricSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'analysis_run_id' => $this->analysis_run_id,
            'consistency_score' => $this->consistency_score,
            'diversity_score' => $this->diversity_score,
            'contribution_score' => $this->contribution_score,
            'collaboration_score' => $this->collaboration_score,
            'testing_score' => $this->testing_score,
            'documentation_score' => $this->documentation_score,
            'momentum_score' => $this->momentum_score,
            'score_breakdown' => $this->score_breakdown,
            'evidence' => $this->evidence,
        ];
    }
}
