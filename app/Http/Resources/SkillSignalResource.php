<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SkillSignal */
class SkillSignalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'analysis_run_id' => $this->analysis_run_id,
            'skill_key' => $this->skill_key,
            'score' => $this->score,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
            'notes' => $this->notes,
        ];
    }
}
