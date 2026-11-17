<?php

namespace App\Http\Controllers;

use App\Jobs\SynchronizeSource;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\DataSource;
use App\Models\Indicator;
use App\Models\RiskRule;
use App\Models\StressScenario;
use App\Models\SyncRun;
use App\Services\Analytics;
use App\Services\AuditService;
use App\Services\BiProvinceRegistry;
use App\Services\BiSekiProvincialAdapter;
use App\Services\DatasetImporter;
use App\Services\FinancialQuery;
use App\Services\OfficialDownload;
use App\Services\QueueHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class FinancialController extends Controller
{
    public function analytics(Request $request, Analytics $analytics): Response
    {
        $input = $request->validate(['sector' => 'nullable|in:banking,inclusion,consumer,macro', 'year' => 'nullable|integer|min:1900|max:2200', 'scope' => 'nullable|string|max:100']);
        $year = isset($input['year']) ? (int) $input['year'] : null;
        $sector = $input['sector'] ?? null;

        $scope = $input['scope'] ?? (in_array($sector, ['inclusion', 'consumer'], true) ? null : 'Indonesia');

        return response()->json(['data' => $analytics->series($sector, $year, $scope), 'signals' => $analytics->signals($year, $sector, $scope), 'disclaimer' => 'Analisis independen dari publikasi resmi; bukan rating OJK atau rekomendasi investasi.']);
    }

    public function observations(Request $request): Response
    {
        $input = $request->validate(['indicator' => 'nullable|string|max:64', 'source' => 'nullable|integer', 'year' => 'nullable|integer|min:1900|max:2200', 'scope' => 'nullable|string|max:100', 'sector' => 'nullable|in:banking,inclusion,consumer,macro', 'page' => 'nullable|integer|min:1|max:10000', 'per_page' => 'nullable|integer|min:1|max:100']);
        $query = FinancialQuery::published()->select('o.*', 'd.name as dataset_name');
        foreach (['indicator' => 'indicator_code', 'scope' => 'scope', 'sector' => 'sector'] as $parameter => $column) {
            if (filled($input[$parameter] ?? null)) {
                $query->where('o.'.$column, $input[$parameter]);
            }
        }
        if (isset($input['source'])) {
            $query->where('d.data_source_id', $input['source']);
        }
        if (isset($input['year'])) {
            $query->whereBetween('o.period_date', [$input['year'].'-01-01', $input['year'].'-12-31']);
        }
        if ($request->is('*/export')) {
            abort_if($query->count() > 20000, 422, 'Persempit filter ekspor hingga maksimal 20.000 baris.');

            return response()->streamDownload(function () use ($query): void {
                $file = fopen('php://output', 'wb');
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, DatasetImporter::COLUMNS, ',', '"', '');
                $labels = Indicator::pluck('label', 'code');
                foreach ($query->orderBy('o.id')->cursor() as $row) {
                    $cells = [];
                    foreach (DatasetImporter::COLUMNS as $column) {
                        $value = $column === 'label' ? ($labels[$row->indicator_code] ?? $row->indicator_code) : ($row->$column ?? '');
                        $cells[] = is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                    }
                    fputcsv($file, $cells, ',', '"', '');
                }
                fclose($file);
            }, 'finwatch-observations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->json($query->orderByDesc('o.period_date')->orderByDesc('o.id')->paginate($input['per_page'] ?? 25)->through(function ($row): array {
            $region = in_array($row->indicator_code, ['bi_credit_provincial', 'bi_deposits_provincial'], true) ? app(BiProvinceRegistry::class)->find($row->scope) : null;

            return array_replace((array) $row, ['value' => (float) $row->value, 'scope_label' => $region['name'] ?? $row->scope]);
        }));
    }

    public function indicators(): Response
    {
        return response()->json(['data' => Indicator::orderBy('label')->get()]);
    }

    public function indicatorSave(Request $request, ?Indicator $indicator = null): Response
    {
        $input = $request->validate(['code' => ['required', 'regex:/^[a-z][a-z0-9_]{1,63}$/', Rule::unique('indicators')->ignore($indicator?->id)], 'label' => 'required|string|max:200', 'unit' => 'required|string|max:64', 'frequency' => 'required|in:monthly,quarterly,annual,ytd,snapshot', 'sector' => 'required|in:banking,inclusion,consumer,macro', 'change_type' => 'required|in:level,ratio', 'definition' => 'required|string|max:3000', 'methodology_note' => 'nullable|string|max:3000']);
        if ($indicator && DB::table('indicator_observations')->where('indicator_code', $indicator->code)->exists() && ($indicator->code !== $input['code'] || $indicator->unit !== $input['unit'] || $indicator->frequency !== $input['frequency'] || $indicator->sector !== $input['sector'])) {
            throw ValidationException::withMessages(['unit' => 'Definisi seri yang sudah digunakan tidak boleh diubah; buat kode baru.']);
        }
        $indicator ? $indicator->update($input) : $indicator = Indicator::create($input);
        AuditService::record('indicator.saved', $request->user(), ['reason' => $indicator->code]);

        return response()->json(['data' => $indicator->fresh()]);
    }

    public function sources(): Response
    {
        return response()->json(['data' => DataSource::orderBy('id')->get()]);
    }

    public function sourceSave(Request $request, DataSource $source, OfficialDownload $download): Response
    {
        $input = $request->validate(['name' => 'required|string|max:200', 'url' => 'required|url:https|max:2000', 'description' => 'required|string|max:3000', 'method' => 'required|in:manual,official_file,official_api', 'adapter' => 'required|in:manual,normalized_csv,normalized_xlsx,ojk_spi_2025_06,bi_seki_provincial_v1', 'download_url' => 'nullable|url:https|max:2000', 'frequency' => 'nullable|string|max:32', 'enabled' => 'required|boolean']);
        abort_if($input['adapter'] === 'ojk_spi_2025_06' && $source->code !== 'OJK', 422, 'Profil SPI hanya tersedia untuk sumber OJK.');
        abort_if($input['adapter'] === BiSekiProvincialAdapter::PROFILE && ($source->code !== 'BI' || ($input['download_url'] ?? null) !== BiSekiProvincialAdapter::SOURCES['credit']['url']), 422, 'Profil SEKI provinsi memakai sumber BI dan dua tautan tabel resmi yang sudah diverifikasi.');
        $hosts = match ($source->code) {
            'OJK','SNLIK','PEPK' => ['ojk.go.id', 'www.ojk.go.id', 'data.ojk.go.id'], 'BI' => ['bi.go.id', 'www.bi.go.id'], 'BPS' => ['bps.go.id', 'www.bps.go.id', 'webapi.bps.go.id'], default => []
        };
        $download->validateUrl($input['url'], $hosts);
        if (filled($input['download_url'] ?? null)) {
            $download->validateUrl($input['download_url'], $hosts);
        }
        if ($input['adapter'] !== 'manual' && empty($input['download_url'])) {
            throw ValidationException::withMessages(['download_url' => 'Adapter otomatis memerlukan URL unduhan yang sudah diverifikasi.']);
        }
        $source->update($input + ['allowed_hosts' => $hosts, 'status' => $input['adapter'] === 'manual' ? 'manual' : 'configured']);
        AuditService::record('source.saved', $request->user(), ['source_id' => $source->id]);

        return response()->json(['data' => $source]);
    }

    public function datasets(Request $request): Response
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:10000']);

        return response()->json(Dataset::with('source:id,code,name')->withCount('versions')->orderByDesc('id')->paginate(20));
    }

    public function datasetSave(Request $request): Response
    {
        $input = $request->validate(['code' => 'required|string|regex:/^[a-z0-9-]{2,100}$/|unique:datasets', 'name' => 'required|string|max:200', 'data_source_id' => 'required|integer|exists:data_sources,id', 'description' => 'nullable|string|max:3000']);
        $dataset = Dataset::create($input + ['status' => 'draft']);
        AuditService::record('dataset.created', $request->user(), ['dataset_id' => $dataset->id]);

        return response()->json(['data' => $dataset], 201);
    }

    public function versions(Dataset $dataset): Response
    {
        return response()->json(['data' => $dataset->versions()->orderByDesc('number')->get()->map(fn ($version) => ['id' => $version->id, 'number' => $version->number, 'status' => $version->status, 'checksum' => $version->checksum, 'count' => count($version->rows), 'issues' => $version->issues, 'published_at' => $version->published_at, 'created_at' => $version->created_at])]);
    }

    public function version(DatasetVersion $version): Response
    {
        return response()->json(['data' => ['id' => $version->id, 'dataset_id' => $version->dataset_id, 'number' => $version->number, 'status' => $version->status, 'checksum' => $version->checksum, 'rows' => array_slice($version->rows, 0, 100), 'count' => count($version->rows), 'issues' => $version->issues]]);
    }

    public function import(Request $request, Dataset $dataset, DatasetImporter $importer): Response
    {
        $request->validate(['file' => 'required|file|max:10240|extensions:csv,xlsx']);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = $importer->parse($file->getRealPath(), $extension);
        $path = $file->storeAs('snapshots', Str::uuid().'.'.$extension, 'local');
        $version = $importer->stage($dataset, $rows, $path, $request->user());

        return response()->json(['data' => ['id' => $version->id, 'number' => $version->number, 'status' => $version->status, 'count' => count($version->rows), 'issues' => $version->issues]], 201);
    }

    public function publish(Request $request, DatasetVersion $version, DatasetImporter $importer): Response
    {
        $version = $importer->publish($version, $request->user());
        SyncRun::where('version_id', $version->id)->where('status', 'needs_review')->update(['status' => 'success', 'progress' => 100, 'finished_at' => now()]);

        return response()->json(['data' => ['id' => $version->id, 'status' => $version->status, 'published_at' => $version->published_at], 'signals' => app(Analytics::class)->signals()]);
    }

    public function template(): Response
    {
        return response()->streamDownload(function (): void {
            $file = fopen('php://output', 'wb');
            fputcsv($file, DatasetImporter::COLUMNS, ',', '"', '');
            fclose($file);
        }, 'finwatch-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function syncRuns(Request $request): Response
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:10000']);

        $runs = SyncRun::with('source:id,code,name')->latest('id')->paginate(20);

        return response()->json(array_merge($runs->toArray(), ['worker' => QueueHealth::status()]));
    }

    public function syncRun(SyncRun $run): Response
    {
        return response()->json(['data' => $run->load('source:id,code,name')]);
    }

    public function sync(Request $request): Response
    {
        abort_if(json_decode(DB::table('system_settings')->where('key', 'sync_enabled')->value('value') ?? 'true', true) === false, 423, 'Sinkronisasi dinonaktifkan pengelola.');
        $input = $request->validate(['source_id' => 'nullable|integer|exists:data_sources,id']);
        $runs = [];
        foreach (DataSource::where('enabled', true)->when(isset($input['source_id']), fn ($q) => $q->whereKey($input['source_id']))->get() as $source) {
            $run = DB::transaction(function () use ($source, $request) {
                DataSource::whereKey($source->id)->lockForUpdate()->firstOrFail();

                return SyncRun::where('data_source_id', $source->id)->whereIn('status', ['queued', 'checking', 'downloading', 'parsing', 'validating', 'retrying'])->first()
                    ?? SyncRun::create(['data_source_id' => $source->id, 'requested_by' => $request->user()->id, 'logs' => []]);
            });
            if ($run->wasRecentlyCreated) {
                SynchronizeSource::dispatch($run->id);
                AuditService::record('sync.queued', $request->user(), ['sync_run_id' => $run->id, 'source_id' => $source->id]);
            }
            $runs[] = $run;
        }

        return response()->json(['data' => $runs], 202);
    }

    public function retry(Request $request, SyncRun $run): Response
    {
        DB::transaction(function () use ($request, $run): void {
            $run = SyncRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($run->status, ['failed', 'manual_required'], true), 409, 'Hanya proses gagal/manual yang dapat diulang.');
            $run->update(['status' => 'queued', 'progress' => 0, 'attempt' => $run->attempt + 1, 'requested_by' => $request->user()->id]);
            SynchronizeSource::dispatch($run->id)->afterCommit();
            AuditService::record('sync.retried', $request->user(), ['sync_run_id' => $run->id]);
        });

        return response()->json(['data' => $run->fresh()], 202);
    }

    public function rules(): Response
    {
        return response()->json(['data' => RiskRule::orderBy('id')->get()]);
    }

    public function ruleSave(Request $request, ?RiskRule $rule = null): Response
    {
        $input = $request->validate(['name' => 'required|string|max:200', 'indicator_code' => 'required|exists:indicators,code', 'operator' => 'required|in:gt,lt,change_gt', 'threshold' => 'required|numeric|between:-1000000000,1000000000', 'minimum_samples' => 'required|integer|min:2|max:240', 'severity' => 'required|in:watch,elevated,high', 'enabled' => 'required|boolean', 'methodology' => 'required|string|max:3000']);
        DB::transaction(function () use (&$rule, $input, $request): void {
            if ($rule) {
                DB::table('system_settings')->updateOrInsert(['key' => 'risk_rule_history_'.$rule->id.'_'.$rule->version], ['value' => json_encode($rule->toArray()), 'created_at' => now(), 'updated_at' => now()]);
                $rule->update($input + ['version' => $rule->version + 1]);
            } else {
                $rule = RiskRule::create($input);
            }
            AuditService::record('risk.rule_saved', $request->user(), ['rule_id' => $rule->id, 'revision' => $rule->version]);
        });

        return response()->json(['data' => $rule]);
    }

    public function signals(Analytics $analytics): Response
    {
        return response()->json(['data' => $analytics->signals(), 'notice' => 'Sinyal penelitian berbasis aturan; bukan penilaian kesehatan resmi.']);
    }

    public function stress(Request $request, Analytics $analytics): Response
    {
        $input = $request->validate(['credit' => 'required|numeric|min:0.0001|max:1000000000000000', 'npl' => 'required|numeric|between:0,100', 'car' => 'required|numeric|between:0,100', 'npl_shock_pp' => 'required|numeric|between:0,50', 'lgd_percent' => 'required|numeric|between:0,100']);
        if ($input['npl'] + $input['npl_shock_pp'] > 100) {
            throw ValidationException::withMessages(['npl_shock_pp' => 'NPL akhir tidak boleh melebihi 100%.']);
        }

        return response()->json(['data' => $analytics->stress($input)]);
    }

    public function scenarios(Request $request): Response
    {
        return response()->json(['data' => StressScenario::when(! $request->is('*/admin/*'), fn ($q) => $q->where('published', true))->orderBy('name')->get()]);
    }

    public function scenarioSave(Request $request, ?StressScenario $scenario = null): Response
    {
        $input = $request->validate(['name' => 'required|string|max:200', 'parameters' => 'required|array', 'parameters.credit' => 'required|numeric|min:0.0001|max:1000000000000000', 'parameters.npl' => 'required|numeric|between:0,100', 'parameters.car' => 'required|numeric|between:0,100', 'parameters.npl_shock_pp' => 'required|numeric|between:0,50', 'parameters.lgd_percent' => 'required|numeric|between:0,100', 'methodology' => 'required|string|max:3000', 'published' => 'required|boolean']);
        if ($input['parameters']['npl'] + $input['parameters']['npl_shock_pp'] > 100) {
            throw ValidationException::withMessages(['parameters' => 'NPL akhir melebihi 100%.']);
        }
        $scenario ? $scenario->update($input) : $scenario = StressScenario::create($input + ['created_by' => $request->user()->id]);
        AuditService::record('stress.scenario_saved', $request->user(), ['scenario_id' => $scenario->id]);

        return response()->json(['data' => $scenario]);
    }
}
