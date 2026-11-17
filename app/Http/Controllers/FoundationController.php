<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Services\FinancialQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FoundationController extends Controller
{
    private function publishedObservations(): Builder
    {
        return FinancialQuery::published();
    }

    public function overview(): JsonResponse
    {
        return response()->json([
            'published_datasets' => DB::table('datasets')->where('status', 'published')->where('published_at', '<=', now())->count(),
            'observations' => $this->publishedObservations()->count(),
            'latest_period' => $this->publishedObservations()->max('period_date'),
            'years' => $this->yearsList(),
            'sources_count' => DataSource::count(),
        ]);
    }

    private function yearsList(): array
    {
        return $this->publishedObservations()->select('period_date')->distinct()->orderByDesc('period_date')->pluck('period_date')
            ->map(fn (string $date): int => (int) substr($date, 0, 4))->unique()->values()->all();
    }

    public function years(): JsonResponse
    {
        return response()->json(['data' => $this->yearsList()]);
    }

    public function sources(): JsonResponse
    {
        return response()->json(['data' => DataSource::orderBy('id')->get(['id', 'code', 'name', 'url', 'method', 'status', 'description', 'last_checked_at'])]);
    }

    public function site(): JsonResponse
    {
        $tagline = DB::table('system_settings')->where('key', 'site_tagline')->value('value');

        return response()->json(['site_tagline' => $tagline ? json_decode($tagline, true) : 'Data publik. Konteks terbuka.']);
    }
}
