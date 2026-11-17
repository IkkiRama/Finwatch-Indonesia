<?php

namespace App\Services\SourceAdapters;

class BpsAdapter extends OfficialAdapter
{
    public function hosts(): array
    {
        return ['bps.go.id', 'www.bps.go.id', 'webapi.bps.go.id'];
    }
}
