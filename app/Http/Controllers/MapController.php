<?php

namespace App\Http\Controllers;

use App\Services\MapService;
use App\Services\PlanMatrix;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MapController extends Controller
{
    public function index(Request $request, MapService $maps, PlanMatrix $matrix)
    {
        Gate::authorize('view-matrix');
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');
        $year = $request->integer('year') ?: $matrix->defaultYear();
        $mode = in_array($request->query('mode'), ['extension', 'matrix'], true) ? $request->query('mode') : 'extension';

        $metrics = $maps->woredaMetrics($user, $period->from, $period->to, $year);

        return view('map.index', [
            'period' => $period,
            'year' => $year,
            'years' => $matrix->availableYears(),
            'mode' => $mode,
            'metrics' => $metrics,
        ]);
    }
}
