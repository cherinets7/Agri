<?php

namespace App\Http\Controllers;

use App\Enums\PlanUnit;
use App\Models\PlanActivity;
use App\Models\StrategicGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Zone-only: strategic goals and the activities (rows) of the weekly matrix. */
class PlanStructureController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-plan');

        return view('plan.structure', [
            'goals' => StrategicGoal::query()->with('activities')->orderBy('sort_order')->orderBy('number')->get(),
            'units' => PlanUnit::options(),
        ]);
    }

    public function storeGoal(Request $request): RedirectResponse
    {
        Gate::authorize('manage-plan');
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'name_am' => ['required', 'string', 'max:255'],
        ]);

        StrategicGoal::create($data + ['sort_order' => $data['number']]);

        return back()->with('status', 'ስትራተጂክ ግቡ ተጨምሯል።');
    }

    public function updateGoal(Request $request, StrategicGoal $goal): RedirectResponse
    {
        Gate::authorize('manage-plan');
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'name_am' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $goal->update([
            'number' => $data['number'],
            'sort_order' => $data['number'],
            'name_am' => $data['name_am'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'ተስተካክሏል።');
    }

    public function storeActivity(Request $request): RedirectResponse
    {
        Gate::authorize('manage-plan');
        $data = $request->validate([
            'strategic_goal_id' => ['required', 'exists:strategic_goals,id'],
            'name_am' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(PlanUnit::class)],
        ]);

        $order = (int) PlanActivity::query()->where('strategic_goal_id', $data['strategic_goal_id'])->max('sort_order');
        PlanActivity::create($data + ['sort_order' => $order + 1]);

        return back()->with('status', 'ተግባሩ ተጨምሯል።');
    }

    public function updateActivity(Request $request, PlanActivity $activity): RedirectResponse
    {
        Gate::authorize('manage-plan');
        $data = $request->validate([
            'name_am' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(PlanUnit::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $activity->update([
            'name_am' => $data['name_am'],
            'unit' => $data['unit'],
            'sort_order' => $data['sort_order'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'ተስተካክሏል።');
    }
}
