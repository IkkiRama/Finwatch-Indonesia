<?php

namespace App\Services\SourceAdapters;

use App\Models\DataSource;

class Registry
{
    public function for(DataSource $source): OfficialAdapter
    {
        return match ($source->code) {
            'OJK' => new OjkAdapter, 'BI' => new BiAdapter, 'BPS' => new BpsAdapter, 'SNLIK' => new SnlikAdapter, 'PEPK' => new PepkAdapter, default => throw new \RuntimeException('Adapter institusi tidak terdaftar.')
        };
    }
}
