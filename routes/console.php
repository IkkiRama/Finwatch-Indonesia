<?php

use App\Jobs\SynchronizeSource;
use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('finwatch:publish-scheduled')->everyMinute()->withoutOverlapping(5);
Schedule::call(function (): void {
    DB::table('system_heartbeats')->updateOrInsert(['key' => 'scheduler'], ['seen_at' => now()]);
})->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping(5);
Schedule::call(function (): void {
    if (json_decode(DB::table('system_settings')->where('key', 'sync_enabled')->value('value') ?? 'true', true) === false) {
        return;
    }
    foreach (DataSource::where('enabled', true)->whereNotNull('download_url')->where('adapter', '!=', 'manual')->get() as $source) {
        DB::transaction(function () use ($source): void {
            DataSource::whereKey($source->id)->lockForUpdate()->firstOrFail();
            if (SyncRun::where('data_source_id', $source->id)->whereIn('status', ['queued', 'checking', 'downloading', 'parsing', 'validating', 'retrying'])->exists()) {
                return;
            }
            $run = SyncRun::create(['data_source_id' => $source->id, 'logs' => []]);
            SynchronizeSource::dispatch($run->id)->afterCommit();
        });
    }
})->dailyAt('06:00')->name('official-source-check')->withoutOverlapping(30);
