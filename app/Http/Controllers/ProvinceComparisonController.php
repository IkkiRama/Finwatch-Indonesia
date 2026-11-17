<?php

namespace App\Http\Controllers;

use App\Services\ProvinceComparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProvinceComparisonController extends Controller
{
    public function catalog(ProvinceComparison $comparison): JsonResponse
    {
        return response()->json($comparison->catalog());
    }

    public function compare(Request $request, ProvinceComparison $comparison): JsonResponse
    {
        $request->validate(['indicator' => ['sometimes', 'string', Rule::in(['literacy', 'inclusion', 'credit', 'deposits'])]]);
        $indicator = (string) $request->query('indicator', 'literacy');
        $banking = in_array($indicator, ['credit', 'deposits'], true);
        $geography = $comparison->geographyFor($indicator);
        $request->merge([
            'a' => $request->query('a', $banking ? 'row-13' : '33'),
            'b' => $request->query('b', $banking ? 'row-12' : '32'),
            'period' => $request->query('period', $comparison->defaultPeriod($indicator)),
            'indicator' => $indicator,
        ]);
        $codes = array_column($comparison->regionsFor($indicator), 'code');
        $input = $request->validate([
            'a' => ['required', 'string', Rule::in($codes)],
            'b' => ['required', 'string', 'different:a', Rule::in($codes)],
            'period' => ['required', 'string', $banking ? 'regex:/^(19|20|21)[0-9]{2}(-(0[1-9]|1[0-2]))?$/' : 'regex:/^(19|20|21)[0-9]{2}$/'],
            'indicator' => ['required', 'string', Rule::in(['literacy', 'inclusion', 'credit', 'deposits'])],
            'code_scheme' => ['sometimes', 'string', Rule::in([$geography['code_scheme']])],
            'geography' => ['sometimes', 'string', Rule::in([$geography['id']])],
        ], ['b.different' => 'Pilih dua provinsi yang berbeda.']);

        return response()->json($comparison->compare($input['a'], $input['b'], $input['period'], $input['indicator']));
    }
}
