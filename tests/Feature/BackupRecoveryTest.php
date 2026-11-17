<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\User;
use App\Services\BackupArchive;
use Database\Seeders\DataSourceSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['finwatch.backup_key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
        $this->seed([RolesAndPermissionsSeeder::class, DataSourceSeeder::class]);
    }

    public function test_encrypted_backup_restores_relational_rows_html_snapshots_and_revokes_sessions(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(DB::table('roles')->where('name', 'reader')->value('id'));
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => 'fixture', 'last_activity' => time()]);
        Storage::disk('local')->put('snapshots/official_snlik.html', '<html>Official fixture archive</html>');
        Storage::disk('local')->put('media/test.webp', 'fixture-media');
        $archive = app(BackupArchive::class)->create();
        $this->assertFileExists($archive);
        $this->assertStringNotContainsString($user->email, file_get_contents($archive));
        $source = DataSource::firstOrFail();
        $original = $source->name;
        $source->update(['name' => 'Changed after backup']);
        Storage::disk('local')->delete('snapshots/official_snlik.html');
        app(BackupArchive::class)->restore($archive, true);
        $this->assertDatabaseHas('data_sources', ['id' => $source->id, 'name' => $original]);
        $this->assertDatabaseHas('role_user', ['user_id' => $user->id]);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertSame($user->auth_version + 1, $user->fresh()->auth_version);
        $this->assertSame('<html>Official fixture archive</html>', Storage::disk('local')->get('snapshots/official_snlik.html'));
        $this->assertSame('fixture-media', Storage::disk('local')->get('media/test.webp'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.backup_restored']);
        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }

    public function test_restore_requires_explicit_replace_for_an_occupied_database(): void
    {
        $archive = app(BackupArchive::class)->create();
        try {
            app(BackupArchive::class)->restore($archive);
            $this->fail('Expected occupied-target rejection');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('--replace', $exception->getMessage());
        }
        $this->assertDatabaseCount('data_sources', 5);
    }

    public function test_tampered_backup_and_wrong_key_are_rejected_before_database_mutation(): void
    {
        $archive = app(BackupArchive::class)->create();
        $ciphertext = file_get_contents($archive);
        config(['finwatch.backup_key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
        try {
            app(BackupArchive::class)->restore($archive, true);
            $this->fail('Expected wrong-key rejection');
        } catch (DecryptException) {
            $this->assertDatabaseCount('data_sources', 5);
        }
        config(['finwatch.backup_key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
        file_put_contents($archive, substr($ciphertext, 0, -4).'FAIL');
        $this->expectException(DecryptException::class);
        app(BackupArchive::class)->restore($archive, true);
    }
}
