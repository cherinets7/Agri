<?php

namespace App\Services;

use App\Enums\PerformanceBand;
use App\Enums\Role;
use App\Models\ExtensionReport;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExtensionPerformance
{
    public static function band(float|int|null $value): PerformanceBand
    {
        if ($value === null) {
            return PerformanceBand::None;
        }

        return match (true) {
            $value < config('extension.low_below') => PerformanceBand::Low,
            $value < config('extension.target_from') => PerformanceBand::Acceptable,
            $value <= config('extension.target_to') => PerformanceBand::Target,
            default => PerformanceBand::Over,
        };
    }

    public static function isWorkDay(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeekIso, config('extension.work_days'), true);
    }

    public static function workDays(CarbonInterface $from, CarbonInterface $to): int
    {
        if ($to->lt($from)) {
            return 0;
        }

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->filter(fn ($d) => self::isWorkDay($d))
            ->count();
    }

    public static function lastWorkDay(?CarbonInterface $today = null): Carbon
    {
        $day = Carbon::instance(($today ?? now())->copy())->startOfDay()->subDay();
        while (! self::isWorkDay($day)) {
            $day->subDay();
        }

        return $day;
    }

    /** Active kebele experts a viewer is responsible for. */
    public function experts(User $viewer, ?int $woredaId = null)
    {
        $query = User::query()
            ->visibleTo($viewer)
            ->where('role', Role::Kebele->value)
            ->where('is_active', true)
            ->with('location.parent');

        if ($woredaId) {
            $query->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id'));
        }

        return $query;
    }

    /**
     * Per-expert performance for a period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byExpert(User $viewer, CarbonInterface $from, CarbonInterface $to, ?int $woredaId = null): Collection
    {
        $experts = $this->experts($viewer, $woredaId)->orderBy('name')->get();
        $totals = ExtensionReport::query()
            ->visibleTo($viewer)
            ->counted()
            ->between($from, $to)
            ->whereIn('user_id', $experts->pluck('id'))
            ->selectRaw('user_id, COUNT(*) as days, SUM(farmers_total) as farmers, SUM(female_farmers) as female, SUM(youth_farmers) as youth, SUM(duration_minutes) as minutes')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $effectiveTo = $to->copy()->min(now());
        $expectedDays = self::workDays($from, $effectiveTo);

        return $experts->map(function (User $expert) use ($totals, $expectedDays) {
            $row = $totals->get($expert->id);
            $days = (int) ($row->days ?? 0);
            $farmers = (int) ($row->farmers ?? 0);
            $avg = $days > 0 ? round($farmers / $days, 1) : null;

            return [
                'user' => $expert,
                'kebele' => $expert->location,
                'woreda' => $expert->location?->parent,
                'days_reported' => $days,
                'expected_days' => $expectedDays,
                'missing_days' => max(0, $expectedDays - $days),
                'farmers' => $farmers,
                'female' => (int) ($row->female ?? 0),
                'youth' => (int) ($row->youth ?? 0),
                'minutes' => (int) ($row->minutes ?? 0),
                'avg' => $avg,
                'band' => self::band($avg),
            ];
        });
    }

    /**
     * Zone view: roll the expert rows up per woreda.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function byWoreda(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($r) => $r['woreda']?->id)->map(function (Collection $group) {
            $days = $group->sum('days_reported');
            $farmers = $group->sum('farmers');
            $avg = $days > 0 ? round($farmers / $days, 1) : null;

            return [
                'woreda' => $group->first()['woreda'],
                'experts' => $group->count(),
                'days_reported' => $days,
                'missing_days' => $group->sum('missing_days'),
                'farmers' => $farmers,
                'avg' => $avg,
                'band' => self::band($avg),
                'low_experts' => $group->filter(fn ($r) => $r['band'] === PerformanceBand::Low)->count(),
            ];
        })->sortBy(fn ($r) => $r['woreda']?->name_am)->values();
    }

    /** Experts with no submitted report for the given working day. */
    public function missingOn(User $viewer, CarbonInterface $day, ?int $woredaId = null): Collection
    {
        $experts = $this->experts($viewer, $woredaId)->orderBy('name')->get();
        $reported = ExtensionReport::query()
            ->visibleTo($viewer)
            ->counted()
            ->whereDate('report_date', $day->toDateString())
            ->pluck('user_id');

        return $experts->reject(fn (User $u) => $reported->contains($u->id))->values();
    }
}
