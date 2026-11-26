<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\PerformanceBand;
use App\Enums\ReportStatus;
use App\Models\ExtensionReport;
use App\Models\Location;
use App\Services\ExtensionPerformance;
use App\Support\Period;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ExtensionPerformance $performance)
    {
        $user = $request->user();
        $period = Period::fromRequest($request);

        $woredas = collect();
        $woredaId = null;
        if ($user->isZone()) {
            $woredas = Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get();
            $woredaId = $request->integer('woreda') ?: null;
        } elseif ($user->isWoreda()) {
            $woredaId = $user->location_id;
        }

        $rows = $performance->byExpert($user, $period->from, $period->to, $woredaId);

        $reports = ExtensionReport::query()->visibleTo($user)->between($period->from, $period->to);
        if ($woredaId) {
            $reports->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id'));
        }

        $statusCounts = (clone $reports)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $queue = ExtensionReport::query()->visibleTo($user)->with(['user', 'location.parent']);
        if ($user->isWoreda()) {
            $queue->where('status', ReportStatus::Submitted->value);
        } elseif ($user->isZone()) {
            $queue->where('status', ReportStatus::WoredaVerified->value);
        } else {
            $queue->where('status', ReportStatus::Returned->value);
        }
        $queueCount = (clone $queue)->count();
        $queueItems = $queue->orderBy('report_date')->limit(8)->get();

        $lastWorkDay = ExtensionPerformance::lastWorkDay();
        $missing = $user->isKebele()
            ? collect()
            : $performance->missingOn($user, $lastWorkDay, $woredaId);

        $bandCounts = $rows->countBy(fn ($r) => $r['band']->value);

        return view('dashboard', [
            'period' => $period,
            'woredas' => $woredas,
            'woredaId' => $woredaId,
            'rows' => $rows,
            'woredaRows' => $user->isZone() && ! $woredaId ? $performance->byWoreda($rows) : collect(),
            'statusCounts' => $statusCounts,
            'queueCount' => $queueCount,
            'queueItems' => $queueItems,
            'lastWorkDay' => $lastWorkDay,
            'missing' => $missing,
            'bandCounts' => $bandCounts,
            'totalFarmers' => $rows->sum('farmers'),
            'totalReports' => $statusCounts->except([ReportStatus::Draft->value, ReportStatus::Returned->value])->sum(),
            'alerts' => $rows->filter(fn ($r) => in_array($r['band'], [PerformanceBand::Low, PerformanceBand::Over], true))->sortBy('avg')->values(),
            'todayReport' => $user->isKebele()
                ? ExtensionReport::query()->where('user_id', $user->id)->whereDate('report_date', today())->first()
                : null,
        ]);
    }
}
