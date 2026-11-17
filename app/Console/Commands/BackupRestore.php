<?php

namespace App\Console\Commands;

use App\Services\BackupArchive;
use Illuminate\Console\Command;

class BackupRestore extends Command
{
    protected $signature = 'finwatch:restore {path} {--replace : Ganti isi database target} {--confirm= : Wajib RESTORE untuk menjalankan pemulihan}';

    protected $description = 'Pulihkan backup terenkripsi pada skema yang sama dan cabut seluruh sesi.';

    public function handle(BackupArchive $archive): int
    {
        if ($this->option('confirm') !== 'RESTORE') {
            $this->error('Pemulihan mengganti data target. Wajib --confirm=RESTORE dan --replace jika target berisi data.');

            return self::FAILURE;
        }
        $archive->restore((string) $this->argument('path'), (bool) $this->option('replace'));
        $this->info('Pemulihan selesai; semua sesi dicabut.');

        return self::SUCCESS;
    }
}
