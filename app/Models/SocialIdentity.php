<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialIdentity extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_subject', 'verified_email_at'];

    protected $hidden = ['provider_subject'];

    protected function casts(): array
    {
        return ['verified_email_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
