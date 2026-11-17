<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupArchive
{
    private const EXCLUDED = ['migrations', 'sessions', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks'];

    private function tables(): array
    {
        $schema = DB::getDriverName() === 'mysql' ? DB::getDatabaseName() : null;

        return array_values(array_diff(Schema::getTableListing($schema, false), self::EXCLUDED));
    }

    private function cipher(): Encrypter
    {
        $value = config('finwatch.backup_key') ?: config('app.key');
        $key = str_starts_with($value, 'base64:') ? base64_decode(substr($value, 7), true) : $value;
        if (! is_string($key) || strlen($key) !== 32) {
            throw new \RuntimeException('Kunci backup harus 32 byte; gunakan APP_KEY atau FINWATCH_BACKUP_KEY base64 yang disimpan terpisah.');
        }

        return new Encrypter($key, 'AES-256-CBC');
    }

    public function create(): string
    {
        $disk = Storage::disk('local');
        $name = 'backups/'.now()->format('Ymd-His').'-'.Str::uuid();
        $disk->makeDirectory('backups');
        $zipPath = $disk->path($name.'.zip');
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new \RuntimeException('Arsip backup tidak dapat dibuat.');
        }
        $open = true;
        $temporary = [];
        $manifest = ['format' => 1, 'created_at' => now()->toIso8601String(), 'tables' => [], 'assets' => []];
        try {
            DB::transaction(function () use (&$manifest, &$temporary, $disk, $zip): void {
                foreach ($this->tables() as $table) {
                    if (in_array($table, self::EXCLUDED, true) || ! preg_match('/^[a-z0-9_]+$/', $table)) {
                        continue;
                    }
                    $manifest['tables'][$table] = Schema::getColumnListing($table);
                    $path = $disk->path('backups/'.Str::uuid().'.jsonl');
                    $temporary[] = $path;
                    $handle = fopen($path, 'wb');
                    try {
                        foreach (DB::table($table)->cursor() as $row) {
                            fwrite($handle, json_encode((array) $row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
                            if (ftell($handle) > 100 * 1024 * 1024) {
                                throw new \RuntimeException('Tabel backup melampaui batas 100 MB.');
                            }
                        }
                    } finally {
                        fclose($handle);
                    }
                    $zip->addFile($path, 'tables/'.$table.'.jsonl');
                }
            });
            foreach (['media', 'snapshots'] as $directory) {
                foreach ($disk->files($directory) as $asset) {
                    if (! preg_match('~^(media|snapshots)/[A-Za-z0-9_-]+\.(webp|csv|xlsx|html)$~', $asset)) {
                        continue;
                    }
                    $manifest['assets'][$asset] = hash_file('sha256', $disk->path($asset));
                    $zip->addFile($disk->path($asset), 'assets/'.$asset);
                }
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            $zip->close();
            $open = false;
            if (filesize($zipPath) > 25 * 1024 * 1024) {
                throw new \RuntimeException('Backup aplikasi dibatasi 25 MB arsip. Gunakan backup cPanel/database dan media streaming untuk instalasi lebih besar.');
            }
            $encrypted = $this->cipher()->encryptString(file_get_contents($zipPath));
            $disk->put($name.'.finwatch', $encrypted);

            return $disk->path($name.'.finwatch');
        } finally {
            if ($open) {
                $zip->close();
            }
            foreach ($temporary as $file) {
                @unlink($file);
            }
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
        }
    }

    public function restore(string $path, bool $replace = false): void
    {
        if (! is_file($path) || filesize($path) > 40 * 1024 * 1024) {
            throw new \RuntimeException('Backup tidak tersedia atau melampaui batas ukuran.');
        }
        $bytes = $this->cipher()->decryptString(file_get_contents($path));
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $temporary = $disk->path('backups/'.Str::uuid().'.zip');
        file_put_contents($temporary, $bytes);
        unset($bytes);
        $zip = new ZipArchive;
        $open = false;
        $originalAssets = [];
        try {
            if ($zip->open($temporary) !== true) {
                throw new \RuntimeException('Arsip backup tidak valid.');
            }
            $open = true;
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $total += $entry['size'];
                if ($total > 100 * 1024 * 1024) {
                    throw new \RuntimeException('Isi arsip melampaui batas aman.');
                }
            }
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 64, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 1 || ! is_array($manifest['tables'] ?? null) || ! is_array($manifest['assets'] ?? null)) {
                throw new \RuntimeException('Format manifest tidak didukung.');
            }
            $current = $this->tables();
            if (array_diff($current, array_keys($manifest['tables'])) || array_diff(array_keys($manifest['tables']), $current)) {
                throw new \RuntimeException('Skema backup berbeda. Pulihkan pada versi aplikasi/migrasi yang sama.');
            }
            foreach ($manifest['tables'] as $table => $columns) {
                if (! preg_match('/^[a-z0-9_]+$/', $table) || $columns !== Schema::getColumnListing($table) || $zip->locateName('tables/'.$table.'.jsonl') === false) {
                    throw new \RuntimeException('Kolom/tabel backup tidak cocok.');
                }
                if (! $replace && DB::table($table)->exists()) {
                    throw new \RuntimeException('Target berisi data. Gunakan --replace hanya untuk pemulihan yang disengaja.');
                }
            }
            foreach ($manifest['assets'] as $asset => $checksum) {
                if (! preg_match('~^(media|snapshots)/[A-Za-z0-9_-]+\.(webp|csv|xlsx|html)$~', $asset) || $zip->locateName('assets/'.$asset) === false || hash('sha256', $zip->getFromName('assets/'.$asset)) !== $checksum) {
                    throw new \RuntimeException('Path/checksum aset tidak valid.');
                }
            }
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($manifest, $zip, $disk, &$originalAssets): void {
                    foreach (array_keys($manifest['tables']) as $table) {
                        DB::table($table)->delete();
                    }
                    foreach ($manifest['tables'] as $table => $columns) {
                        $stream = $zip->getStream('tables/'.$table.'.jsonl');
                        try {
                            while (($line = fgets($stream)) !== false) {
                                $row = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
                                if (! is_array($row) || array_diff(array_keys($row), $columns) || array_diff($columns, array_keys($row))) {
                                    throw new \RuntimeException('Kolom baris backup tidak cocok.');
                                } DB::table($table)->insert($row);
                            }
                        } finally {
                            fclose($stream);
                        }
                    }
                    DB::table('sessions')->delete();
                    DB::table('users')->increment('auth_version');
                    foreach ($manifest['assets'] as $asset => $checksum) {
                        $originalAssets[$asset] = $disk->exists($asset) ? $disk->get($asset) : null;
                        $disk->put($asset, $zip->getFromName('assets/'.$asset));
                    }
                });
            } catch (\Throwable $exception) {
                foreach ($originalAssets as $asset => $content) {
                    $content === null ? $disk->delete($asset) : $disk->put($asset, $content);
                }
                throw $exception;
            } finally {
                Schema::enableForeignKeyConstraints();
            }
            AuditService::record('system.backup_restored', null, ['channel' => 'cli', 'count' => count($manifest['tables'])]);
        } finally {
            if ($open) {
                $zip->close();
            } @unlink($temporary);
        }
    }
}
