<?php

namespace App\Services\SourceAdapters;

use App\Models\DataSource;
use App\Services\OjkSpiAdapter;

class OjkAdapter extends OfficialAdapter
{
    public function hosts(): array
    {
        return ['ojk.go.id', 'www.ojk.go.id', 'data.ojk.go.id'];
    }

    public function parse(string $path, string $extension, DataSource $source): array
    {
        return $source->adapter === 'ojk_spi_2025_06' ? app(OjkSpiAdapter::class)->parse($path, $source->download_url) : parent::parse($path, $extension, $source);
    }
}
