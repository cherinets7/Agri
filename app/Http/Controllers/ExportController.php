<?php

namespace App\Http\Controllers;

use App\Enums\PeriodicReportType;
use App\Enums\SectorType;
use App\Exports\MatrixExport;
use App\Exports\PerformanceExport;
use App\Exports\PeriodicReportsExport;
use App\Exports\ReportsExport;
use App\Exports\SectorReportsExport;
use App\Services\ExtensionPerformance;
use App\Services\PeriodicAggregation;
use App\Services\PlanMatrix;
use App\Services\SectorAggregation;
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

    public function periodic(Request $request, string $type, PeriodicAggregation $aggregation)
    {
        $reportType = PeriodicReportType::from($type);
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);

        $query = PeriodicReportController::filteredQuery($request, $reportType, $period);
        $summary = $aggregation->summarize($user, $reportType, $period->from, $period->to, $woredaId, $request->boolean('approved'));

        return Excel::download(
            new PeriodicReportsExport($reportType, $query, $summary),
            "ሪፖርት-{$reportType->number()}-{$period->from->toDateString()}_{$period->to->toDateString()}.xlsx",
        );
    }

    public function sector(Request $request, string $sector, SectorAggregation $aggregation)
    {
        $sectorType = SectorType::from($sector);
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);

        $query = SectorReportController::filteredQuery($request, $sectorType, $period);
        $summary = $aggregation->summarize($user, $sectorType, $period->from, $period->to, $woredaId, $request->boolean('approved'));

        return Excel::download(
            new SectorReportsExport($sectorType, $query, $summary),
            "ዘርፍ-{$sectorType->value}-{$period->from->toDateString()}_{$period->to->toDateString()}.xlsx",
        );
    }
}
