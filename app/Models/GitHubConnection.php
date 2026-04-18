<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GitHubConnection extends Model
{
    use HasFactory;

    protected $table = 'github_connections';

    protected $fillable = [
        'user_id',
        'github_username',
        'analysis_mode',
        'access_token',
        'sync_status',
        'sync_error',
        'connected_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(AnalysisRun::class, 'github_connection_id');
    }
}
