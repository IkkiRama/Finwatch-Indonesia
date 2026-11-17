<?php

namespace App\Console\Commands;

use App\Services\BackupArchive;
use Illuminate\Console\Command;

class BackupCreate extends Command
{
    protected $signature = 'finwatch:backup';

    protected $description = 'Buat arsip database dan aset terenkripsi di storage privat; simpan kunci terpisah.';

    public function handle(BackupArchive $archive): int
    {
        $this->info($archive->create());

        return self::SUCCESS;
    }
}
