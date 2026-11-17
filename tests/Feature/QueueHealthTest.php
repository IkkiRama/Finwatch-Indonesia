<?php

namespace Tests\Feature;

use App\Services\QueueHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QueueHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_worker_or_stale_heartbeat_is_not_reported_as_running(): void
    {
        $this->assertFalse(QueueHealth::status()['active']);
        DB::table('system_heartbeats')->insert(['key' => 'queue-worker', 'seen_at' => now()->subMinutes(3)]);
        $this->assertFalse(QueueHealth::status()['active']);
        DB::table('system_heartbeats')->where('key', 'queue-worker')->update(['seen_at' => now()->addMinutes(3)]);
        $this->assertFalse(QueueHealth::status()['active']);
    }

    public function test_real_worker_loop_updates_heartbeat_and_reports_waiting_and_reserved_separately(): void
    {
        DB::table('jobs')->insert([
            ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'default', 'payload' => '{}', 'attempts' => 1, 'reserved_at' => time(), 'available_at' => time(), 'created_at' => time()],
        ]);
        Event::dispatch(new Looping('database', 'default'));
        $status = QueueHealth::status();
        $this->assertTrue($status['active']);
        $this->assertSame(1, $status['pending_jobs']);
        $this->assertSame(1, $status['reserved_jobs']);
        $this->assertNotNull($status['last_seen_at']);
    }
}
