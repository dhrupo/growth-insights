<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AnalysisRecommendation */
class AnalysisRecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'analysis_run_id' => $this->analysis_run_id,
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
        ];
    }
}
