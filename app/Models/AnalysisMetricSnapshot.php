<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisMetricSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_run_id',
        'consistency_score',
        'diversity_score',
        'contribution_score',
        'collaboration_score',
        'testing_score',
        'documentation_score',
        'momentum_score',
        'score_breakdown',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'consistency_score' => 'decimal:2',
            'diversity_score' => 'decimal:2',
            'contribution_score' => 'decimal:2',
            'collaboration_score' => 'decimal:2',
            'testing_score' => 'decimal:2',
            'documentation_score' => 'decimal:2',
            'momentum_score' => 'decimal:2',
            'score_breakdown' => 'array',
            'evidence' => 'array',
        ];
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
