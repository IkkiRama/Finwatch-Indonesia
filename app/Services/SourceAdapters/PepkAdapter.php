<?php

namespace App\Services\SourceAdapters;

class PepkAdapter extends OfficialAdapter
{
    public function hosts(): array
    {
        return ['ojk.go.id', 'www.ojk.go.id'];
    }
}
