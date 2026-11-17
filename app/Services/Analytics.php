<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\RiskRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

class Analytics
{
    public function series(?string $sector = null, ?int $year = null, ?string $scope = null): array
    {
        $rows = FinancialQuery::published()->when($sector, fn (Builder $q) => $q->where('o.sector', $sector))
            ->when($scope, fn (Builder $q) => $q->where('o.scope', $scope))
            ->when($year, fn (Builder $q) => $q->whereBetween('o.period_date', [$year.'-01-01', $year.'-12-31']))
            ->orderBy('o.period_date')->select('o.*', 'd.name as dataset_name')->limit(20000)->get();
        $definitions = Indicator::all()->keyBy('code');

        return $rows->groupBy(fn ($row) => $row->dataset_id.'|'.$row->indicator_code.'|'.$row->scope.'|'.$row->segment.'|'.$row->frequency)->map(function ($group) use ($definitions): array {
            $latest = $group->last();
            $previous = $group->count() > 1 ? $group[$group->count() - 2] : null;
            $definition = $definitions[$latest->indicator_code] ?? null;
            $comparable = $previous && $this->validLag($previous->period_date, $latest->period_date, $latest->frequency) && $previous->unit === $latest->unit && $previous->methodology_note === $latest->methodology_note;
            $change = $comparable ? ($latest->unit === '%' ? (float) $latest->value - (float) $previous->value : ((float) $previous->value > 0 ? 100 * ((float) $latest->value / (float) $previous->value - 1) : null)) : null;
            $history = $group->slice(max(0, $group->count() - 25), -1)->values();
            $consistent = $history->count() >= 12;
            $prior = null;
            foreach ($history->concat([$latest]) as $point) {
                if ($point->unit !== $latest->unit || $point->methodology_note !== $latest->methodology_note || ($prior && ! $this->validLag($prior->period_date, $point->period_date, $latest->frequency))) {
                    $consistent = false;
                }
                $prior = $point;
            }
            $mean = $consistent ? $history->avg(fn ($point) => (float) $point->value) : null;
            $std = $consistent ? sqrt($history->sum(fn ($point) => pow((float) $point->value - $mean, 2)) / $history->count()) : null;
            $statistics = ['samples' => $history->count(), 'z_score' => $std > 0 ? ((float) $latest->value - $mean) / $std : null, 'mean' => $mean, 'standard_deviation' => $std, 'lower_band' => $std > 0 ? $mean - 2 * $std : null, 'upper_band' => $std > 0 ? $mean + 2 * $std : null, 'method' => 'Deskriptif, maksimum 24 observasi sebelum titik terbaru; minimum 12, periode berurutan dan definisi sama. Simpangan baku populasi; pita mean ± 2σ bukan batas regulator atau interval prediksi.'];

            return ['dataset_id' => $latest->dataset_id, 'dataset_name' => $latest->dataset_name, 'code' => $latest->indicator_code, 'label' => $definition?->label ?? $latest->indicator_code, 'unit' => $latest->unit, 'frequency' => $latest->frequency, 'scope' => $latest->scope, 'segment' => $latest->segment, 'sector' => $latest->sector,
                'latest' => (float) $latest->value, 'period' => $latest->period_date, 'release_date' => $latest->release_date, 'retrieved_at' => $latest->retrieved_at, 'source_url' => $latest->source_url, 'dataset_version' => $latest->dataset_version, 'methodology_note' => $latest->methodology_note,
                'change' => $change, 'change_unit' => $latest->unit === '%' ? 'pp' : '%', 'comparable' => (bool) $comparable, 'statistics' => $statistics,
                'points' => $group->map(fn ($point) => ['period' => $point->period_date, 'value' => (float) $point->value, 'version' => $point->dataset_version])->values()->all()];
        })->values()->all();
    }

    private function validLag(string $previous, string $latest, string $frequency): bool
    {
        $months = match ($frequency) {
            'monthly' => 1,'quarterly' => 3,'annual' => 12,default => null
        };

        return $months !== null && CarbonImmutable::parse($previous)->startOfMonth()->addMonths($months)->format('Y-m') === substr($latest, 0, 7);
    }

    public function signals(?int $year = null, ?string $sector = null, ?string $scope = null): array
    {
        $series = collect($this->series($sector, $year, $scope));

        return RiskRule::where('enabled', true)->get()->flatMap(function (RiskRule $rule) use ($series) {
            return $series->where('code', $rule->indicator_code)->map(function (array $data) use ($rule): array {
                $enough = count($data['points']) >= $rule->minimum_samples;
                $comparable = $rule->operator !== 'change_gt' || ($data['comparable'] && $data['change'] !== null);
                $value = $rule->operator === 'change_gt' ? $data['change'] : $data['latest'];
                $triggered = $enough && $comparable && match ($rule->operator) {
                    'gt','change_gt' => $value > $rule->threshold, 'lt' => $value < $rule->threshold, default => false
                };

                return ['rule_id' => $rule->id, 'name' => $rule->name, 'version' => $rule->version, 'indicator_code' => $rule->indicator_code, 'dataset_id' => $data['dataset_id'], 'segment' => $data['segment'], 'scope' => $data['scope'], 'period' => $data['period'], 'value' => $value, 'unit' => $rule->operator === 'change_gt' ? $data['change_unit'] : $data['unit'], 'latest_value' => $data['latest'], 'threshold' => (float) $rule->threshold, 'operator' => $rule->operator, 'samples' => count($data['points']), 'minimum_samples' => $rule->minimum_samples, 'status' => ! $enough ? 'insufficient_data' : (! $comparable ? 'incomparable_data' : ($triggered ? 'triggered' : 'within_rule')), 'severity' => $rule->severity, 'methodology' => $rule->methodology, 'source_url' => $data['source_url'], 'dataset_version' => $data['dataset_version'], 'evaluated_at' => now()->toIso8601String(), 'model_version' => 'rules-1.0'];
            });
        })->values()->all();
    }

    public function stress(array $parameters): array
    {
        $credit = (float) $parameters['credit'];
        $npl = (float) $parameters['npl'];
        $car = (float) $parameters['car'];
        $shock = (float) $parameters['npl_shock_pp'];
        $lgd = (float) $parameters['lgd_percent'] / 100;
        $capital = $credit * $car / 100;
        $loss = $credit * $shock / 100 * $lgd;

        return ['baseline' => compact('credit', 'npl', 'car'), 'parameters' => $parameters, 'additional_loss' => $loss, 'capital_before' => $capital, 'capital_after' => $capital - $loss, 'car_after' => 100 * ($capital - $loss) / $credit, 'npl_after' => $npl + $shock,
            'formula' => 'Tambahan kerugian = kredit × (shock NPL pp / 100) × LGD; modal awal = kredit × CAR / 100; CAR simulasi = 100 × (modal awal − kerugian) / kredit.',
            'assumptions' => 'Kredit dipakai sebagai proksi ATMR; tidak memperhitungkan profit, provisi awal, bobot risiko, mitigasi atau respons perilaku. Ini sensitivitas hipotetis, bukan perhitungan CAR regulator atau ramalan institusi.', 'model_version' => 'sensitivity-1.0'];
    }
}
