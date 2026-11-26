<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\WeeklyPlanStatus;
use App\Models\Location;
use App\Models\WeeklyPlanReport;
use App\Services\KebeleWeekly;
use App\Services\PlanMatrix;
use App\Services\WeeklyPlanWorkflow;
use App\Support\EthiopianDate;
use App\Support\Signature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WeeklyPlanReportController extends Controller
{
    public function __construct(private WeeklyPlanWorkflow $workflow, private PlanMatrix $matrix, private KebeleWeekly $kebeleWeekly) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', WeeklyPlanReport::class);
        $user = $request->user();

        $reports = WeeklyPlanReport::query()->visibleTo($user)->with(['woreda', 'user'])
            ->when($request->filled('year'), fn ($q) => $q->where('fiscal_year', $request->integer('year')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($user->isZone() && $request->filled('woreda'), fn ($q) => $q->where('woreda_id', $request->integer('woreda')))
            ->orderByDesc('week_start')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('weekly.index', [
            'reports' => $reports,
            'years' => $this->matrix->availableYears(),
            'statuses' => WeeklyPlanStatus::options(),
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', WeeklyPlanReport::class);
        $user = $request->user();

        $week = $this->parseWeek($request->query('week')) ?? PlanMatrix::weekStart(today());

        $existing = WeeklyPlanReport::query()->where('woreda_id', $user->location_id)->whereDate('week_start', $week)->first();
        if ($existing) {
            return redirect()->route($existing->isEditable() ? 'weekly.edit' : 'weekly.show', $existing)
                ->with('status', 'ለዚህ ሳምንት ሪፖርት አስቀድሞ አለ።');
        }

        $report = new WeeklyPlanReport([
            'woreda_id' => $user->location_id,
            'week_start' => $week,
            'fiscal_year' => EthiopianDate::fiscalYear($week),
        ]);

        return view('weekly.form', $this->formData($report));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WeeklyPlanReport::class);
        $user = $request->user();

        $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
            ...$this->valueRules(),
        ]);

        $week = PlanMatrix::weekStart(Carbon::createFromFormat('Y-m-d', $request->input('week_start')));
        if ($week->gt(PlanMatrix::weekStart(today()))) {
            throw ValidationException::withMessages(['week_start' => 'ወደፊት ሳምንት ሪፖርት ማድረግ አይቻልም።']);
        }
        if (WeeklyPlanReport::query()->where('woreda_id', $user->location_id)->whereDate('week_start', $week)->exists()) {
            throw ValidationException::withMessages(['week_start' => 'ለዚህ ሳምንት ሪፖርት አስቀድሞ አለ።']);
        }

        $report = DB::transaction(function () use ($request, $user, $week) {
            $report = WeeklyPlanReport::create([
                'woreda_id' => $user->location_id,
                'user_id' => $user->id,
                'fiscal_year' => EthiopianDate::fiscalYear($week),
                'week_start' => $week,
                'status' => WeeklyPlanStatus::Draft,
                'notes' => $request->input('notes'),
                'signature_data' => $request->input('signature_data'),
            ]);
            $this->saveActuals($report, (array) $request->input('values', []));
            $this->workflow->record($report, $user, 'create', null, WeeklyPlanStatus::Draft);

            return $report;
        });

        return $this->afterSave($request, $report);
    }

    public function show(WeeklyPlanReport $weeklyReport)
    {
        $this->authorize('view', $weeklyReport);

        return view('weekly.show', $this->formData($weeklyReport->load(['woreda', 'user', 'approver', 'actions.user'])));
    }

    public function edit(WeeklyPlanReport $weeklyReport)
    {
        $this->authorize('update', $weeklyReport);

        return view('weekly.form', $this->formData($weeklyReport->load('woreda')));
    }

    public function update(Request $request, WeeklyPlanReport $weeklyReport): RedirectResponse
    {
        $this->authorize('update', $weeklyReport);
        $request->validate($this->valueRules());

        DB::transaction(function () use ($request, $weeklyReport) {
            $weeklyReport->update([
                'notes' => $request->input('notes'),
                'signature_data' => $request->input('signature_data') ?: $weeklyReport->signature_data,
            ]);
            $this->saveActuals($weeklyReport, (array) $request->input('values', []));
        });

        return $this->afterSave($request, $weeklyReport);
    }

    public function destroy(WeeklyPlanReport $weeklyReport): RedirectResponse
    {
        $this->authorize('delete', $weeklyReport);
        $weeklyReport->delete();

        return redirect()->route('weekly.index')->with('status', 'ረቂቁ ተሰርዟል።');
    }

    public function submit(Request $request, WeeklyPlanReport $weeklyReport): RedirectResponse
    {
        $this->authorize('submit', $weeklyReport);

        return $this->doSubmit($request, $weeklyReport);
    }

    public function approve(Request $request, WeeklyPlanReport $weeklyReport): RedirectResponse
    {
        $this->authorize('approve', $weeklyReport);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'signature_data' => Signature::rules(),
        ]);

        $this->workflow->approve($weeklyReport, $request->user(), $data['note'] ?? null, $data['signature_data'] ?? null);

        return redirect()->route('weekly.show', $weeklyReport)->with('status', 'ሪፖርቱ ጸድቋል።');
    }

    public function return(Request $request, WeeklyPlanReport $weeklyReport): RedirectResponse
    {
        $this->authorize('return', $weeklyReport);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->workflow->return($weeklyReport, $request->user(), $data['reason']);

        return redirect()->route('weekly.show', $weeklyReport)->with('status', 'ሪፖርቱ ወደ ወረዳ ተመልሷል።');
    }

    private function afterSave(Request $request, WeeklyPlanReport $report): RedirectResponse
    {
        if ($request->input('action') === 'submit') {
            return $this->doSubmit($request, $report);
        }

        return redirect()->route('weekly.show', $report)->with('status', 'ረቂቁ ተቀምጧል።');
    }

    private function doSubmit(Request $request, WeeklyPlanReport $report): RedirectResponse
    {
        try {
            $sig = Signature::fromRequest($request) ?: $report->signature_data;
            $this->workflow->submit($report, $request->user(), $sig);
        } catch (InvalidArgumentException) {
            return redirect()->route('weekly.edit', $report)->withErrors(['values' => 'ከመላክዎ በፊት ቢያንስ አንድ ክንውን ያስገቡ።']);
        }

        return redirect()->route('weekly.show', $report)->with('status', 'ሪፖርቱ ለዞን ተልኳል።');
    }

    private function valueRules(): array
    {
        return [
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'signature_data' => Signature::rules(),
        ];
    }

    /** Blank input = nothing reported (row removed); 0 is stored as a real zero. */
    private function saveActuals(WeeklyPlanReport $report, array $values): void
    {
        $activityIds = $this->matrix->activeGoals()->flatMap(fn ($g) => $g->activities->pluck('id'))->all();

        foreach ($activityIds as $id) {
            $value = $values[$id] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $report->actuals()->where('plan_activity_id', $id)->delete();
            } else {
                $report->actuals()->updateOrCreate(['plan_activity_id' => $id], ['value' => $value]);
            }
        }
    }

    private function formData(WeeklyPlanReport $report): array
    {
        $before = $this->matrix->cumulativeFor($report->woreda_id, $report->fiscal_year, $report->week_start, $report->id);

        $kebeleSums = [];
        $coverage = null;
        if (KebeleWeekly::enabled() && Gate::allows('view-kebele-weekly')) {
            $kebeleSums = $this->kebeleWeekly->sumsForWoreda($report->woreda_id, $report->week_start);
            $coverage = $this->kebeleWeekly->coverage($report->woreda_id, $report->week_start);
        }

        return [
            'kebeleSums' => $kebeleSums,
            'kebeleCoverage' => $coverage,
            'report' => $report,
            'woreda' => $report->woreda ?? Location::find($report->woreda_id),
            'goals' => $this->matrix->activeGoals(),
            'targets' => $this->matrix->woredaTargets($report->woreda_id, $report->fiscal_year),
            'before' => $before,
            'current' => $report->exists ? $report->actuals()->pluck('value', 'plan_activity_id')->all() : $kebeleSums,
        ];
    }

    private function parseWeek(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return PlanMatrix::weekStart(Carbon::createFromFormat('Y-m-d', $value));
        } catch (\Throwable) {
            return null;
        }
    }
}
