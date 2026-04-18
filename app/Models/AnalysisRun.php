<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalysisRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'github_connection_id',
        'status',
        'source_window_start',
        'source_window_end',
        'started_at',
        'completed_at',
        'overall_score',
        'confidence',
        'summary',
        'evidence_summary',
    ];

    protected function casts(): array
    {
        return [
            'source_window_start' => 'date',
            'source_window_end' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'overall_score' => 'decimal:2',
            'confidence' => 'decimal:2',
        ];
    }

    public function githubConnection(): BelongsTo
    {
        return $this->belongsTo(GitHubConnection::class, 'github_connection_id');
    }

    public function metricSnapshot(): HasOne
    {
        return $this->hasOne(AnalysisMetricSnapshot::class);
    }

    public function weeklyBuckets(): HasMany
    {
        return $this->hasMany(AnalysisWeeklyBucket::class);
    }

    public function skillSignals(): HasMany
    {
        return $this->hasMany(SkillSignal::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(AnalysisRecommendation::class);
    }
}
