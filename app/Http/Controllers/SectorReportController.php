<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\PeriodGranularity;
use App\Enums\ReportStatus;
use App\Enums\SectorType;
use App\Models\Location;
use App\Models\SectorReport;
use App\Services\SectorAggregation;
use App\Services\SectorReportWorkflow;
use App\Support\EthiopianDate;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SectorReportController extends Controller
{
    public function __construct(
        private SectorReportWorkflow $workflow,
        private SectorAggregation $aggregation,
    ) {}

    public function hub(Request $request)
    {
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');

        $cards = collect(SectorType::cases())->map(function (SectorType $sector) use ($user, $period) {
            $base = SectorReport::query()->visibleTo($user)->where('sector', $sector->value);

            return [
                'sector' => $sector,
                'count_period' => (clone $base)->counted()->between($period->from, $period->to)->count(),
                'pending' => (clone $base)->where('status', $user->isWoreda() ? ReportStatus::Submitted->value : ($user->isZone() ? ReportStatus::WoredaVerified->value : ReportStatus::Returned->value))->count(),
            ];
        });

        return view('sectors.hub', [
            'cards' => $cards,
            'period' => $period,
        ]);
    }

    public static function filteredQuery(Request $request, SectorType $sector, Period $period)
    {
        $user = $request->user();
        $query = SectorReport::query()
            ->visibleTo($user)
            ->where('sector', $sector->value)
            ->with(['user', 'location.parent'])
            ->between($period->from, $period->to);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($periodType = $request->query('period_type')) {
            $query->where('period_type', $periodType);
        }

        $woredaId = $user->isWoreda() ? $user->location_id : ($request->integer('woreda') ?: null);
        if ($woredaId && ! $user->isKebele()) {
            $query->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id'));
        }

        return $query->orderByDesc('report_date')->orderByDesc('id');
    }

    public function index(Request $request, string $sector)
    {
        $sectorType = SectorType::from($sector);
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);
        $approvedOnly = $request->boolean('approved');

        $reports = self::filteredQuery($request, $sectorType, $period)->paginate(20)->withQueryString();
        $summary = $this->aggregation->summarize($user, $sectorType, $period->from, $period->to, $woredaId, $approvedOnly);

        return view('sectors.index', [
            'sectorType' => $sectorType,
            'reports' => $reports,
            'summary' => $summary,
            'period' => $period,
            'statuses' => ReportStatus::options(),
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
        ]);
    }

    public function create(string $sector)
    {
        $this->authorize('create', SectorReport::class);
        $sectorType = SectorType::from($sector);

        $report = new SectorReport([
            'sector' => $sectorType,
            'period_type' => PeriodGranularity::Weekly,
            'report_date' => today(),
            'payload' => [],
        ]);

        return view('sectors.form', [
            'sectorType' => $sectorType,
            'report' => $report,
            'granularities' => PeriodGranularity::options(),
        ]);
    }

    public function store(Request $request, string $sector): RedirectResponse
    {
        $this->authorize('create', SectorReport::class);
        $sectorType = SectorType::from($sector);
        $user = $request->user();

        $validated = $this->validated($request, $sectorType);
        $reportDate = Carbon::createFromFormat('Y-m-d', $validated['report_date']);

        $report = SectorReport::create([
            'sector' => $sectorType,
            'period_type' => $validated['period_type'],
            'report_date' => $reportDate,
            'fiscal_year' => EthiopianDate::fiscalYear($reportDate),
            'user_id' => $user->id,
            'location_id' => $user->location_id,
            'status' => ReportStatus::Draft,
            'payload' => $validated['payload'],
            'notes' => $validated['notes'],
        ]);
        $this->workflow->record($report, $user, 'create', null, ReportStatus::Draft);

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($report, $user);

            return redirect()->route('sectors.show', $report)->with('status', 'የዘርፍ ሪፖርቱ ለወረዳ ተልኳል።');
        }

        return redirect()->route('sectors.show', $report)->with('status', 'ረቂቁ ተቀምጧል።');
    }

    public function show(SectorReport $sectorReport)
    {
        $this->authorize('view', $sectorReport);
        $sectorReport->load(['user', 'location.parent', 'actions.user', 'verifier', 'approver']);

        return view('sectors.show', [
            'report' => $sectorReport,
            'sectorType' => $sectorReport->sector,
        ]);
    }

    public function edit(SectorReport $sectorReport)
    {
        $this->authorize('update', $sectorReport);

        return view('sectors.form', [
            'sectorType' => $sectorReport->sector,
            'report' => $sectorReport,
            'granularities' => PeriodGranularity::options(),
        ]);
    }

    public function update(Request $request, SectorReport $sectorReport): RedirectResponse
    {
        $this->authorize('update', $sectorReport);
        $validated = $this->validated($request, $sectorReport->sector);
        $reportDate = Carbon::createFromFormat('Y-m-d', $validated['report_date']);

        $sectorReport->update([
            'period_type' => $validated['period_type'],
            'report_date' => $reportDate,
            'fiscal_year' => EthiopianDate::fiscalYear($reportDate),
            'payload' => $validated['payload'],
            'notes' => $validated['notes'],
        ]);

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($sectorReport, $request->user());

            return redirect()->route('sectors.show', $sectorReport)->with('status', 'የዘርፍ ሪፖርቱ ለወረዳ ተልኳል።');
        }

        return redirect()->route('sectors.show', $sectorReport)->with('status', 'ለውጡ ተቀምጧል።');
    }

    public function destroy(SectorReport $sectorReport): RedirectResponse
    {
        $this->authorize('delete', $sectorReport);
        $sector = $sectorReport->sector->value;
        $sectorReport->delete();

        return redirect()->route('sectors.index', $sector)->with('status', 'ረቂቁ ተሰርዟል።');
    }

    public function submit(Request $request, SectorReport $sectorReport): RedirectResponse
    {
        $this->authorize('submit', $sectorReport);
        $this->workflow->submit($sectorReport, $request->user());

        return redirect()->route('sectors.show', $sectorReport)->with('status', 'የዘርፍ ሪፖርቱ ለወረዳ ተልኳል።');
    }

    public function verify(Request $request, SectorReport $sectorReport): RedirectResponse
    {
        $this->authorize('verify', $sectorReport);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
        $this->workflow->verify($sectorReport, $request->user(), $note);

        return redirect()->route('sectors.show', $sectorReport)->with('status', 'ሪፖርቱ በወረዳ ተረጋግጦ ወደ ዞን ተላልፏል።');
    }

    public function approve(Request $request, SectorReport $sectorReport): RedirectResponse
    {
        $this->authorize('approve', $sectorReport);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
        $this->workflow->approve($sectorReport, $request->user(), $note);

        return redirect()->route('sectors.show', $sectorReport)->with('status', 'ሪፖርቱ በዞን ጸድቋል።');
    }

    public function return(Request $request, SectorReport $sectorReport): RedirectResponse
    {
        $this->authorize('return', $sectorReport);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->workflow->return($sectorReport, $request->user(), $data['reason']);

        return redirect()->route('sectors.show', $sectorReport)->with('status', 'ሪፖርቱ ወደ ቀበሌ ተመልሷል።');
    }

    private function validated(Request $request, SectorType $sector): array
    {
        $rules = [
            'report_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'period_type' => ['required', Rule::enum(PeriodGranularity::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        $attributes = [
            'report_date' => 'ቀን',
            'period_type' => 'የሪፖርት ጊዜ',
            'notes' => 'ማስታወሻ',
        ];

        $fields = $sector->fields();
        foreach ($fields as $key => $field) {
            if (! empty($field['computed'])) {
                continue;
            }
            $req = ! empty($field['required']) ? 'required' : 'nullable';
            $fieldRules = [$req];
            if ($field['type'] === 'int') {
                $fieldRules = array_merge($fieldRules, ['integer', 'min:0', 'max:10000000']);
            } elseif ($field['type'] === 'decimal') {
                $fieldRules = array_merge($fieldRules, ['numeric', 'min:0', 'max:100000000']);
            } elseif ($field['type'] === 'select') {
                $fieldRules[] = Rule::in(array_keys($field['options']));
            } else {
                $fieldRules = array_merge($fieldRules, ['string', 'max:255']);
            }

            $rules["payload.{$key}"] = $fieldRules;
            $attributes["payload.{$key}"] = $field['label'];
        }

        $data = $request->validate($rules, [], $attributes);
        $raw = (array) ($data['payload'] ?? []);
        $payload = [];

        foreach ($fields as $key => $field) {
            if (! empty($field['computed'])) {
                continue;
            }
            $val = $raw[$key] ?? null;
            if ($field['type'] === 'int') {
                $payload[$key] = $val === null || $val === '' ? 0 : (int) $val;
            } elseif ($field['type'] === 'decimal') {
                $payload[$key] = $val === null || $val === '' ? 0.0 : round((float) $val, 3);
            } else {
                $payload[$key] = $val;
            }
        }

        if ($sector === SectorType::Irrigation) {
            $payload['beneficiaries_total'] = $payload['beneficiaries_male'] + $payload['beneficiaries_female'];
        } elseif ($sector === SectorType::Belg) {
            $payload['total_ha'] = round($payload['maize_ha'] + $payload['vegetables_ha'] + $payload['wheat_ha'], 3);
        }

        return [
            'report_date' => $data['report_date'],
            'period_type' => $data['period_type'],
            'payload' => $payload,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
