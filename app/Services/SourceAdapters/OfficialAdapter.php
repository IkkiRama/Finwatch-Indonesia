<?php

namespace App\Services\SourceAdapters;

use App\Models\DataSource;
use App\Services\DatasetImporter;

abstract class OfficialAdapter
{
    abstract public function hosts(): array;

    public function discover(DataSource $source): ?string
    {
        return $source->adapter === 'manual' ? null : $source->download_url;
    }

    public function parse(string $path, string $extension, DataSource $source): array
    {
        return app(DatasetImporter::class)->parse($path, $extension);
    }
}
