<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'revision'];

    protected $attributes = ['revision' => 1, 'status' => 'draft', 'allow_comments' => true, 'is_featured' => false];

    protected function casts(): array
    {
        return ['sources' => 'array', 'tags' => 'array', 'body_doc' => 'array', 'published_at' => 'datetime', 'scheduled_at' => 'datetime', 'allow_comments' => 'boolean', 'is_featured' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(AuthorProfile::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->lte(now()) && ! $this->trashed();
    }
}
