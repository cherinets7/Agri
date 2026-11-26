<?php

namespace App\Http\Controllers;

use App\Models\KebeleWeeklyActual;
use App\Services\KebeleWeekly;
use App\Services\PlanMatrix;
use App\Support\EthiopianDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Optional kebele-level weekly actuals (config plan.kebele_weekly_entry). The woreda weekly form
 * is pre-filled with the sum of its kebeles; the matrix itself only counts woreda reports.
 */
class KebeleWeeklyController extends Controller
{
    public function __construct(private PlanMatrix $matrix, private KebeleWeekly $kebeleWeekly) {}

    public function edit(Request $request)
    {
        Gate::authorize('enter-kebele-weekly');
        $user = $request->user();
        $week = $this->parseWeek($request->query('week')) ?? PlanMatrix::weekStart(today());

        return view('kebele-weekly.form', [
            'kebele' => $user->location,
            'week' => $week,
            'goals' => $this->matrix->activeGoals(),
            'current' => $this->kebeleWeekly->valuesFor($user->location_id, $week),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('enter-kebele-weekly');
        $user = $request->user();

        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        $week = PlanMatrix::weekStart(Carbon::createFromFormat('Y-m-d', $request->input('week_start')));
        abort_if($week->gt(PlanMatrix::weekStart(today())), 422, 'ወደፊት ሳምንት ማስገባት አይቻልም።');

        $activityIds = $this->matrix->activeGoals()->flatMap(fn ($g) => $g->activities->pluck('id'))->all();
        $values = (array) $request->input('values', []);

        DB::transaction(function () use ($user, $week, $activityIds, $values) {
            foreach ($activityIds as $id) {
                $value = $values[$id] ?? null;
                $query = KebeleWeeklyActual::query()->where(['kebele_id' => $user->location_id, 'plan_activity_id' => $id])
                    ->whereDate('week_start', $week->toDateString());

                if ($value === null || trim((string) $value) === '') {
                    $query->get()->each->delete();

                    continue;
                }

                $row = $query->first();
                if ($row) {
                    $row->update(['value' => $value, 'user_id' => $user->id]);
                } else {
                    KebeleWeeklyActual::create([
                        'kebele_id' => $user->location_id, 'plan_activity_id' => $id, 'user_id' => $user->id,
                        'fiscal_year' => EthiopianDate::fiscalYear($week), 'week_start' => $week->toDateString(), 'value' => $value,
                    ]);
                }
            }
        });

        return redirect()->route('kebele-weekly.edit', ['week' => $week->toDateString()])->with('status', 'የቀበሌ ሳምንታዊ ክንውን ተቀምጧል።');
    }

    private function parseWeek(?string $value): ?Carbon
    {
        try {
            return $value ? PlanMatrix::weekStart(Carbon::createFromFormat('Y-m-d', $value)) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
