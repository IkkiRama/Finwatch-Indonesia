<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinancialQuery
{
    public static function published(): Builder
    {
        return DB::table('indicator_observations as o')->join('datasets as d', 'd.id', '=', 'o.dataset_id')
            ->where('d.status', 'published')->where('d.published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereColumn('o.version_id', 'd.current_version_id')->orWhere(fn (Builder $legacy) => $legacy->whereNull('o.version_id')->whereNull('d.current_version_id')));
    }
}
