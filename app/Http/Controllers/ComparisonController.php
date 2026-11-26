<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Models\Location;
use App\Services\ComparisonService;
use App\Support\EthiopianDate;
use Illuminate\Http\Request;

class ComparisonController extends Controller
{
    public function __invoke(Request $request, ComparisonService $service)
    {
        $user = $request->user();
        $mode = in_array($request->query('mode'), ['month', 'quarter', 'year'], true)
            ? $request->query('mode')
            : 'month';

        $defaultYear = EthiopianDate::fiscalYear(today());
        $fiscalYear = $request->integer('year') ?: $defaultYear;
        [, $curEtMonth] = EthiopianDate::fromGregorian((int) today()->format('Y'), (int) today()->format('n'), (int) today()->format('j'));
        $month = max(1, min(13, $request->integer('month') ?: $curEtMonth));
        $quarter = max(1, min(4, $request->integer('quarter') ?: 1));
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);

        $data = $service->compare($user, $mode, $fiscalYear, $month, $quarter, $woredaId);

        return view('comparisons.index', [
            ...$data,
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
            'woredaId' => $woredaId,
            'months' => EthiopianDate::MONTHS,
            'years' => range($defaultYear - 2, $defaultYear + 1),
        ]);
    }
}
