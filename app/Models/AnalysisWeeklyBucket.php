<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisWeeklyBucket extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_run_id',
        'week_start',
        'week_end',
        'active_days',
        'commits_count',
        'pull_requests_count',
        'issues_count',
        'reviews_count',
        'momentum_label',
        'trend_direction',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'evidence' => 'array',
        ];
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
