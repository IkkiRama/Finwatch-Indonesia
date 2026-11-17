<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'queued', 'progress' => 0, 'attempt' => 1];

    protected function casts(): array
    {
        return ['logs' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
