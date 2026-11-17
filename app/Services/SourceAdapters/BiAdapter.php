<?php

namespace App\Services\SourceAdapters;

class BiAdapter extends OfficialAdapter
{
    public function hosts(): array
    {
        return ['bi.go.id', 'www.bi.go.id'];
    }
}
