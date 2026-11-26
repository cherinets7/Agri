<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Models\Location;
use App\Models\PlanTarget;
use App\Services\PlanMatrix;
use App\Support\EthiopianDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Zone sets the annual zone plan per activity and splits it top-down to the woredas. */
class PlanTargetController extends Controller
{
    public function __construct(private PlanMatrix $matrix) {}

    public function edit(Request $request)
    {
        Gate::authorize('manage-plan');

        $year = $request->integer('year') ?: $this->matrix->defaultYear();
        $years = collect($this->matrix->availableYears())->push($year, EthiopianDate::fiscalYear(today()) + 1)->unique()->sortDesc()->values();
        $zone = Location::query()->ofLevel(Level::Zone)->firstOrFail();
        $targets = PlanTarget::query()->where('fiscal_year', $year)->get()->groupBy('plan_activity_id');

        return view('plan.targets', [
            'year' => $year,
            'years' => $years,
            'zone' => $zone,
            'woredas' => Location::query()->ofLevel(Level::Woreda)->orderBy('id')->get(),
            'goals' => $this->matrix->activeGoals(),
            'targets' => $targets->map(fn ($rows) => $rows->pluck('target', 'location_id')->map(fn ($t) => (float) $t)->all()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-plan');

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'zone' => ['nullable', 'array'],
            'zone.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'woreda' => ['nullable', 'array'],
            'woreda.*' => ['nullable', 'array'],
            'woreda.*.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'distribute' => ['nullable', 'boolean'],
        ]);

        $year = (int) $data['year'];
        $zone = Location::query()->ofLevel(Level::Zone)->firstOrFail();
        $woredaIds = Location::query()->ofLevel(Level::Woreda)->orderBy('id')->pluck('id')->all();
        $activityIds = $this->matrix->activeGoals()->flatMap(fn ($g) => $g->activities->pluck('id'))->all();
        $distribute = (bool) ($data['distribute'] ?? false);

        DB::transaction(function () use ($data, $year, $zone, $woredaIds, $activityIds, $distribute) {
            foreach ($activityIds as $activityId) {
                $zoneValue = $this->clean($data['zone'][$activityId] ?? null);
                $this->store($activityId, $zone->id, $year, $zoneValue);

                $split = [];
                foreach ($woredaIds as $woredaId) {
                    $split[$woredaId] = $this->clean($data['woreda'][$activityId][$woredaId] ?? null);
                }

                if ($distribute && $zoneValue !== null && count($woredaIds) > 0 && collect($split)->every(fn ($v) => $v === null)) {
                    $share = round($zoneValue / count($woredaIds), 3);
                    $split = array_fill_keys($woredaIds, $share);
                }

                foreach ($split as $woredaId => $value) {
                    $this->store($activityId, $woredaId, $year, $value);
                }
            }
        });

        return redirect()->route('plan.edit', ['year' => $year])->with('status', 'ዕቅዱ ተቀምጧል።');
    }

    private function clean(mixed $value): ?float
    {
        return $value === null || trim((string) $value) === '' ? null : (float) $value;
    }

    private function store(int $activityId, int $locationId, int $year, ?float $value): void
    {
        $key = ['plan_activity_id' => $activityId, 'location_id' => $locationId, 'fiscal_year' => $year];

        if ($value === null) {
            PlanTarget::query()->where($key)->delete();
        } else {
            PlanTarget::updateOrCreate($key, ['target' => $value]);
        }
    }
}
