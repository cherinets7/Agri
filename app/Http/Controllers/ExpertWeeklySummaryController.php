<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\ReportStatus;
use App\Models\ExpertWeeklySummary;
use App\Models\Location;
use App\Services\ExpertWeeklySummaryWorkflow;
use App\Services\PlanMatrix;
use App\Support\EthiopianDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExpertWeeklySummaryController extends Controller
{
    public function __construct(private ExpertWeeklySummaryWorkflow $workflow) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = ExpertWeeklySummary::query()
            ->visibleTo($user)
            ->with(['user', 'location.parent']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $woredaId = $user->isWoreda() ? $user->location_id : ($request->integer('woreda') ?: null);
        if ($woredaId && ! $user->isKebele()) {
            $query->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id'));
        }

        $summaries = $query->orderByDesc('week_start')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('summaries.index', [
            'summaries' => $summaries,
            'statuses' => ReportStatus::options(),
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', ExpertWeeklySummary::class);
        $user = $request->user();
        $weekStart = $request->filled('week_start')
            ? PlanMatrix::weekStart(Carbon::parse($request->query('week_start')))
            : PlanMatrix::weekStart(today());

        $metrics = $this->workflow->computeMetrics($user, $weekStart);
        $summary = new ExpertWeeklySummary([
            'week_start' => $weekStart,
            ...$metrics,
        ]);

        return view('summaries.form', ['summary' => $summary]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExpertWeeklySummary::class);
        $user = $request->user();

        $data = $this->validated($request, $user->id, null);
        $weekStart = PlanMatrix::weekStart(Carbon::createFromFormat('Y-m-d', $data['week_start']));
        $metrics = $this->workflow->computeMetrics($user, $weekStart);

        $summary = ExpertWeeklySummary::create([
            'user_id' => $user->id,
            'location_id' => $user->location_id,
            'week_start' => $weekStart,
            'fiscal_year' => EthiopianDate::fiscalYear($weekStart),
            'status' => ReportStatus::Draft,
            ...$metrics,
            'highlights' => $data['highlights'],
            'challenges' => $data['challenges'] ?? null,
            'next_week_plan' => $data['next_week_plan'],
        ]);
        $this->workflow->record($summary, $user, 'create', null, ReportStatus::Draft);

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($summary, $user);

            return redirect()->route('summaries.show', $summary)->with('status', 'ሳምንታዊ ማጠቃለያው ለወረዳ ተልኳል።');
        }

        return redirect()->route('summaries.show', $summary)->with('status', 'ረቂቁ ተቀምጧል።');
    }

    public function show(ExpertWeeklySummary $summary)
    {
        $this->authorize('view', $summary);
        $summary->load(['user', 'location.parent', 'actions.user']);

        return view('summaries.show', ['summary' => $summary]);
    }

    public function edit(ExpertWeeklySummary $summary)
    {
        $this->authorize('update', $summary);

        return view('summaries.form', ['summary' => $summary]);
    }

    public function update(Request $request, ExpertWeeklySummary $summary): RedirectResponse
    {
        $this->authorize('update', $summary);
        $data = $this->validated($request, $summary->user_id, $summary);
        $weekStart = PlanMatrix::weekStart(Carbon::createFromFormat('Y-m-d', $data['week_start']));
        $metrics = $this->workflow->computeMetrics($summary->user, $weekStart);

        $summary->update([
            'week_start' => $weekStart,
            'fiscal_year' => EthiopianDate::fiscalYear($weekStart),
            ...$metrics,
            'highlights' => $data['highlights'],
            'challenges' => $data['challenges'] ?? null,
            'next_week_plan' => $data['next_week_plan'],
        ]);

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($summary, $request->user());

            return redirect()->route('summaries.show', $summary)->with('status', 'ሳምንታዊ ማጠቃለያው ለወረዳ ተልኳል።');
        }

        return redirect()->route('summaries.show', $summary)->with('status', 'ለውጡ ተቀምጧል።');
    }

    public function destroy(ExpertWeeklySummary $summary): RedirectResponse
    {
        $this->authorize('delete', $summary);
        $summary->delete();

        return redirect()->route('summaries.index')->with('status', 'ረቂቁ ተሰርዟል።');
    }

    public function submit(Request $request, ExpertWeeklySummary $summary): RedirectResponse
    {
        $this->authorize('submit', $summary);
        $this->workflow->submit($summary, $request->user());

        return redirect()->route('summaries.show', $summary)->with('status', 'ሳምንታዊ ማጠቃለያው ለወረዳ ተልኳል።');
    }

    public function verify(Request $request, ExpertWeeklySummary $summary): RedirectResponse
    {
        $this->authorize('verify', $summary);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
        $this->workflow->verify($summary, $request->user(), $note);

        return redirect()->route('summaries.show', $summary)->with('status', 'ሳምንታዊ ማጠቃለያው በወረዳ ተረጋግጧል።');
    }

    public function approve(Request $request, ExpertWeeklySummary $summary): RedirectResponse
    {
        $this->authorize('approve', $summary);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
        $this->workflow->approve($summary, $request->user(), $note);

        return redirect()->route('summaries.show', $summary)->with('status', 'ሳምንታዊ ማጠቃለያው በዞን ጸድቋል።');
    }

    public function return(Request $request, ExpertWeeklySummary $summary): RedirectResponse
    {
        $this->authorize('return', $summary);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->workflow->return($summary, $request->user(), $data['reason']);

        return redirect()->route('summaries.show', $summary)->with('status', 'ሳምንታዊ ማጠቃለያው ወደ ቀበሌ ተመልሷል።');
    }

    private function validated(Request $request, int $userId, ?ExpertWeeklySummary $summary): array
    {
        $weekStart = $request->filled('week_start')
            ? PlanMatrix::weekStart(Carbon::parse($request->input('week_start')))->toDateString()
            : null;
        $request->merge(['week_start' => $weekStart]);

        return $request->validate([
            'week_start' => [
                'required', 'date_format:Y-m-d', 'before_or_equal:today',
                Rule::unique('expert_weekly_summaries', 'week_start')->where('user_id', $userId)->ignore($summary?->id),
            ],
            'highlights' => ['required', 'string', 'max:3000'],
            'challenges' => ['nullable', 'string', 'max:2000'],
            'next_week_plan' => ['required', 'string', 'max:3000'],
        ], [], [
            'week_start' => 'የሳምንቱ መጀመሪያ',
            'highlights' => 'የሳምንቱ ዋና ሥራዎች',
            'challenges' => 'ያጋጠሙ ችግሮች',
            'next_week_plan' => 'የሚቀጥለው ሳምንት ዕቅድ',
        ]);
    }
}
