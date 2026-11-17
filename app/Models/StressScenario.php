<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StressScenario extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'published' => 'boolean'];
    }
}
