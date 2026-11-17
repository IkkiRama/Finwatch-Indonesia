<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PublishStarterEditorial as Publisher;
use App\Services\RefreshStarterEditorial;
use Illuminate\Console\Command;
use RuntimeException;

class PublishStarterEditorial extends Command
{
    protected $signature = 'finwatch:publish-starter-editorial {email : Email akun Google pengelola terverifikasi} {--refresh-unchanged : Terapkan edisi terbaru hanya pada naskah bawaan yang belum disunting; simpan revisi dan audit}';

    protected $description = 'Terbitkan enam naskah awal bersumber resmi; slug yang sudah ada tidak ditimpa.';

    public function handle(Publisher $publisher, RefreshStarterEditorial $refresher): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();
        if (! $user) {
            $this->error('Akun tidak ditemukan. Login Google terlebih dahulu; command tidak membuat identitas atau akun baru.');

            return self::FAILURE;
        }
        try {
            $result = $publisher->publish($user);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info(count($result['created']).' artikel diterbitkan; '.count($result['skipped']).' slug yang sudah ada dilewati.');
        if ($this->option('refresh-unchanged')) {
            $refresh = $refresher->refresh($user);
            $this->info(count($refresh['updated']).' naskah bawaan diperbarui dengan revisi baru.');
            foreach ($refresh['skipped'] as $slug => $reason) {
                $this->line($slug.': '.$reason);
            }
        }

        return self::SUCCESS;
    }
}
