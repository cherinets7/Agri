<?php

namespace App\Http\Controllers;

use App\Exports\MatrixExport;
use App\Exports\PerformanceExport;
use App\Exports\ReportsExport;
use App\Services\ExtensionPerformance;
use App\Services\PlanMatrix;
use App\Support\Period;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function reports(Request $request)
    {
        $period = Period::fromRequest($request, 'last30');
        $query = ExtensionReportController::filteredQuery($request, $period);

        return Excel::download(new ReportsExport($query), "ሪፖርቶች-{$period->from->toDateString()}_{$period->to->toDateString()}.xlsx");
    }

    public function performance(Request $request, ExtensionPerformance $performance)
    {
        $user = $request->user();
        $period = Period::fromRequest($request);
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);
        $rows = $performance->byExpert($user, $period->from, $period->to, $woredaId);

        return Excel::download(new PerformanceExport($rows), "አፈጻጸም-{$period->from->toDateString()}_{$period->to->toDateString()}.xlsx");
    }

    public function matrix(Request $request, PlanMatrix $matrix)
    {
        $data = PlanMatrixController::dataFromRequest($request, $matrix);
        $suffix = $data['weekOnly'] ? 'ሳምንት' : 'ድምር';
        $week = $data['through']?->toDateString() ?? 'ዕቅድ';

        return Excel::download(new MatrixExport($data, $data['detail']), "ሳምንታዊ-ማትሪክስ-{$data['year']}-{$suffix}-{$week}.xlsx");
    }
}
