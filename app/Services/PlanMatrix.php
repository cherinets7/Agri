<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\WeeklyPlanStatus;
use App\Models\Location;
use App\Models\PlanActivity;
use App\Models\PlanTarget;
use App\Models\StrategicGoal;
use App\Models\User;
use App\Models\WeeklyPlanReport;
use App\Support\EthiopianDate;
use App\Support\Progress;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the weekly plan / actual performance matrix (ሳምንታዊ ዕቅድ/ክንውን ማትሪክስ):
 * strategic goal -> activity -> zone total (plan, actual) and per-woreda plan/actual.
 * The zone actual is always the sum of the woreda actuals.
 */
class PlanMatrix
{
    public static function weekStart(CarbonInterface $date): Carbon
    {
        return Carbon::instance($date)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    /** Woreda columns a user may see: all for zone, only their own for woreda users. */
    public function woredasFor(User $viewer): Collection
    {
        $query = Location::query()->ofLevel(Level::Woreda)->orderBy('id');

        return match (true) {
            $viewer->isZone() => $query->get(),
            $viewer->isWoreda() => $query->whereKey($viewer->location_id)->get(),
            default => collect(),
        };
    }

    /** @return list<int> */
    public function availableYears(): array
    {
        $years = PlanTarget::query()->distinct()->pluck('fiscal_year')
            ->merge(WeeklyPlanReport::query()->distinct()->pluck('fiscal_year'))
            ->push(EthiopianDate::fiscalYear(today()))
            ->unique()->sortDesc()->values();

        return $years->map(fn ($y) => (int) $y)->all();
    }

    /** Current plan year, or the latest one that already has data. */
    public function defaultYear(): int
    {
        $current = EthiopianDate::fiscalYear(today());
        $hasData = PlanTarget::query()->where('fiscal_year', $current)->exists()
            || WeeklyPlanReport::query()->where('fiscal_year', $current)->exists();

        if ($hasData) {
            return $current;
        }

        $latest = max(
            (int) PlanTarget::query()->max('fiscal_year'),
            (int) WeeklyPlanReport::query()->max('fiscal_year'),
        );

        return $latest ?: $current;
    }

    public function latestWeek(int $year, bool $approvedOnly = false): ?Carbon
    {
        $week = WeeklyPlanReport::query()->counted($approvedOnly)->where('fiscal_year', $year)->max('week_start');

        return $week ? Carbon::parse($week)->startOfDay() : null;
    }

    /** @return array<int, float> activity id => sum of counted weeks strictly before $before */
    public function cumulativeFor(int $woredaId, int $year, CarbonInterface $before, ?int $excludeReportId = null): array
    {
        return $this->actualTotals($year, $before, true, false, $woredaId, $excludeReportId, strictlyBefore: true)
            ->mapWithKeys(fn ($row) => [(int) $row->plan_activity_id => (float) $row->total])
            ->all();
    }

    /** @return array{year:int,through:?Carbon,weekOnly:bool,approvedOnly:bool,showZone:bool,woredas:Collection,goals:list<array>} */
    public function build(User $viewer, int $year, ?CarbonInterface $through = null, bool $weekOnly = false, bool $approvedOnly = false): array
    {
        $woredas = $this->woredasFor($viewer);
        $showZone = $viewer->isZone();
        $through = $through ? self::weekStart($through) : $this->latestWeek($year, $approvedOnly);

        $targets = PlanTarget::query()->where('fiscal_year', $year)->get()->groupBy('plan_activity_id');
        $zoneId = Location::query()->ofLevel(Level::Zone)->value('id');

        $actuals = collect();
        if ($through) {
            $actuals = $this->actualTotals($year, $through, ! $weekOnly, $approvedOnly)
                ->groupBy('plan_activity_id');
        }

        $goals = StrategicGoal::query()->where('is_active', true)
            ->with(['activities' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('number')->get();

        $out = [];
        foreach ($goals as $goal) {
            $rows = [];
            foreach ($goal->activities as $activity) {
                $activityTargets = $targets->get($activity->id, collect())->keyBy('location_id');
                $activityActuals = $actuals->get($activity->id, collect())->keyBy('woreda_id');

                $cells = [];
                foreach ($woredas as $woreda) {
                    $plan = $activityTargets->get($woreda->id)?->target;
                    $actual = $activityActuals->has($woreda->id) ? (float) $activityActuals[$woreda->id]->total : null;
                    $cells[$woreda->id] = [
                        'plan' => $plan,
                        'actual' => $actual,
                        'pct' => $weekOnly ? null : Progress::percent($actual, $plan),
                    ];
                }

                $zonePlan = null;
                $zoneActual = null;
                if ($showZone) {
                    $zonePlan = $activityTargets->get($zoneId)?->target;
                    if ($zonePlan === null && $activityTargets->isNotEmpty()) {
                        $zonePlan = $activityTargets->reject(fn ($t, $locationId) => $locationId === $zoneId)->sum('target');
                    }
                    $zoneActual = $activityActuals->isNotEmpty() ? (float) $activityActuals->sum('total') : null;
                }

                $rows[] = [
                    'activity' => $activity,
                    'zone_plan' => $zonePlan,
                    'zone_actual' => $zoneActual,
                    'zone_pct' => $weekOnly ? null : Progress::percent($zoneActual, $zonePlan),
                    'cells' => $cells,
                ];
            }

            $out[] = ['goal' => $goal, 'rows' => $rows];
        }

        return [
            'year' => $year,
            'through' => $through,
            'weekOnly' => $weekOnly,
            'approvedOnly' => $approvedOnly,
            'showZone' => $showZone,
            'woredas' => $woredas,
            'goals' => $out,
        ];
    }

    /** Sum of weekly actuals per (activity, woreda), counted statuses only. */
    private function actualTotals(int $year, CarbonInterface $week, bool $cumulative, bool $approvedOnly, ?int $woredaId = null, ?int $excludeReportId = null, bool $strictlyBefore = false): Collection
    {
        $statuses = $approvedOnly ? [WeeklyPlanStatus::Approved->value] : WeeklyPlanStatus::counted();
        $date = $week->format('Y-m-d');

        $query = DB::table('weekly_actuals as a')
            ->join('weekly_plan_reports as r', 'r.id', '=', 'a.weekly_plan_report_id')
            ->where('r.fiscal_year', $year)
            ->whereIn('r.status', $statuses)
            ->when($woredaId, fn ($q) => $q->where('r.woreda_id', $woredaId))
            ->when($excludeReportId, fn ($q) => $q->where('r.id', '!=', $excludeReportId))
            ->groupBy('a.plan_activity_id', 'r.woreda_id')
            ->selectRaw('a.plan_activity_id, r.woreda_id, SUM(a.value) as total');

        if ($strictlyBefore) {
            $query->whereDate('r.week_start', '<', $date);
        } elseif ($cumulative) {
            $query->whereDate('r.week_start', '<=', $date);
        } else {
            $query->whereDate('r.week_start', '=', $date);
        }

        return $query->get();
    }

    /** Woreda targets by activity for one woreda: activity id => target. */
    public function woredaTargets(int $woredaId, int $year): array
    {
        return PlanTarget::query()->where('fiscal_year', $year)->where('location_id', $woredaId)
            ->pluck('target', 'plan_activity_id')->map(fn ($t) => (float) $t)->all();
    }

    /** Active activities grouped by goal, for entry / plan forms. */
    public function activeGoals(): Collection
    {
        return StrategicGoal::query()->where('is_active', true)
            ->with(['activities' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('number')->get()
            ->filter(fn ($g) => $g->activities->isNotEmpty())->values();
    }

    public function activityCount(): int
    {
        return PlanActivity::query()->where('is_active', true)->count();
    }
}
