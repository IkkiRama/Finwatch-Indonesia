<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rows' => 'array', 'issues' => 'array', 'published_at' => 'datetime'];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
