<?php

namespace App\Services\SourceAdapters;

class SnlikAdapter extends OfficialAdapter
{
    public function hosts(): array
    {
        return ['ojk.go.id', 'www.ojk.go.id'];
    }
}
