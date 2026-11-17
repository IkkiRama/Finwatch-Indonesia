<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    protected $fillable = ['code', 'name', 'url', 'method', 'status', 'description', 'allowed_hosts', 'download_url', 'adapter', 'frequency', 'enabled', 'last_checksum', 'last_checked_at'];

    protected function casts(): array
    {
        return ['last_checked_at' => 'datetime', 'allowed_hosts' => 'array', 'enabled' => 'boolean'];
    }
}
