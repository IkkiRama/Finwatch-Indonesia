<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class QueueHealth
{
    public static function heartbeat(): void
    {
        DB::table('system_heartbeats')->updateOrInsert(['key' => 'queue-worker'], ['seen_at' => now()]);
    }

    public static function status(): array
    {
        $seen = DB::table('system_heartbeats')->where('key', 'queue-worker')->value('seen_at');
        $age = $seen ? CarbonImmutable::parse($seen)->diffInSeconds(now(), false) : null;

        return [
            'active' => $age !== null && $age >= 0 && $age < 120,
            'last_seen_at' => $seen ? CarbonImmutable::parse($seen)->toIso8601String() : null,
            'pending_jobs' => DB::table('jobs')->whereNull('reserved_at')->count(),
            'reserved_jobs' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
        ];
    }
}
