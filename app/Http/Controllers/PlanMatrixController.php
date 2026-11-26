<?php

namespace App\Http\Controllers;

use App\Services\MatrixQuality;
use App\Services\PlanMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class PlanMatrixController extends Controller
{
    /** @return array<string, mixed> matrix data plus the view options taken from the request */
    public static function dataFromRequest(Request $request, PlanMatrix $matrix): array
    {
        Gate::authorize('view-matrix');

        $years = $matrix->availableYears();
        $year = $request->integer('year') ?: $matrix->defaultYear();
        $weekOnly = $request->query('mode') === 'week';
        $approvedOnly = $request->boolean('approved');

        $through = null;
        if ($request->filled('week')) {
            try {
                $through = Carbon::createFromFormat('Y-m-d', (string) $request->query('week'))->startOfDay();
            } catch (\Throwable) {
                $through = null;
            }
        }

        $data = $matrix->build($request->user(), $year, $through, $weekOnly, $approvedOnly);

        return $data + [
            'years' => $years,
            'detail' => $request->boolean('detail'),
        ];
    }

    public function index(Request $request, PlanMatrix $matrix, MatrixQuality $quality)
    {
        $data = self::dataFromRequest($request, $matrix);
        $data['quality'] = $request->user()->isZone() ? $quality->flags($data) : [];

        return view('matrix.index', $data);
    }
}
