<?php

namespace App\Services;

use App\Enums\Level;
use App\Models\KebeleWeeklyActual;
use App\Models\Location;
use Carbon\CarbonInterface;

class KebeleWeekly
{
    public static function enabled(): bool
    {
        return (bool) config('plan.kebele_weekly_entry');
    }

    /** @return array<int, float> activity id => value for one kebele and week */
    public function valuesFor(int $kebeleId, CarbonInterface $week): array
    {
        return KebeleWeeklyActual::query()->where('kebele_id', $kebeleId)
            ->whereDate('week_start', $week->toDateString())
            ->pluck('value', 'plan_activity_id')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<int, float> activity id => sum over the woreda's kebeles for the week */
    public function sumsForWoreda(int $woredaId, CarbonInterface $week): array
    {
        return KebeleWeeklyActual::query()
            ->whereIn('kebele_id', Location::query()->where('parent_id', $woredaId)->select('id'))
            ->whereDate('week_start', $week->toDateString())
            ->selectRaw('plan_activity_id, SUM(value) as total')->groupBy('plan_activity_id')
            ->pluck('total', 'plan_activity_id')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array{reported:int, total:int} kebeles of the woreda that entered anything for the week */
    public function coverage(int $woredaId, CarbonInterface $week): array
    {
        $kebeleIds = Location::query()->ofLevel(Level::Kebele)->where('parent_id', $woredaId)->pluck('id');

        return [
            'reported' => KebeleWeeklyActual::query()->whereIn('kebele_id', $kebeleIds)
                ->whereDate('week_start', $week->toDateString())->distinct()->count('kebele_id'),
            'total' => $kebeleIds->count(),
        ];
    }
}
