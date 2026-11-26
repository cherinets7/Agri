<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\PeriodGranularity;
use App\Enums\PeriodicReportType;
use App\Enums\ReportStatus;
use App\Models\Location;
use App\Models\PeriodicReport;
use App\Services\PeriodicAggregation;
use App\Services\PeriodicReportWorkflow;
use App\Support\EthiopianDate;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PeriodicReportController extends Controller
{
    public function __construct(
        private PeriodicReportWorkflow $workflow,
        private PeriodicAggregation $aggregation,
    ) {}

    public function hub(Request $request)
    {
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');

        $cards = collect(PeriodicReportType::cases())->map(function (PeriodicReportType $type) use ($user, $period) {
            $base = PeriodicReport::query()->visibleTo($user)->where('report_type', $type->value);

            return [
                'type' => $type,
                'count_period' => (clone $base)->counted()->between($period->from, $period->to)->count(),
                'pending' => (clone $base)->where('status', $user->isWoreda() ? ReportStatus::Submitted->value : ($user->isZone() ? ReportStatus::WoredaVerified->value : ReportStatus::Returned->value))->count(),
            ];
        });

        return view('periodic.hub', [
            'cards' => $cards,
            'period' => $period,
        ]);
    }

    public static function filteredQuery(Request $request, PeriodicReportType $type, Period $period)
    {
        $user = $request->user();
        $query = PeriodicReport::query()
            ->visibleTo($user)
            ->where('report_type', $type->value)
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

    public function index(Request $request, string $type)
    {
        $reportType = PeriodicReportType::from($type);
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);
        $approvedOnly = $request->boolean('approved');

        $reports = self::filteredQuery($request, $reportType, $period)->paginate(20)->withQueryString();
        $summary = $this->aggregation->summarize($user, $reportType, $period->from, $period->to, $woredaId, $approvedOnly);

        $dispatchTargets = collect();
        if ($reportType === PeriodicReportType::ZoneInputs && ! $user->isKebele()) {
            $dispatchTargets = $user->isZone()
                ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get()
                : Location::query()->ofLevel(Level::Kebele)->where('parent_id', $user->location_id)->orderBy('name_am')->get();
        }

        return view('periodic.index', [
            'reportType' => $reportType,
            'reports' => $reports,
            'summary' => $summary,
            'period' => $period,
            'statuses' => ReportStatus::options(),
            'granularities' => PeriodGranularity::options(),
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
            'approvedOnly' => $approvedOnly,
            'dispatchTargets' => $dispatchTargets,
        ]);
    }

    public function create(string $type)
    {
        $this->authorize('create', PeriodicReport::class);
        $reportType = PeriodicReportType::from($type);

        $report = new PeriodicReport([
            'report_type' => $reportType,
            'period_type' => PeriodGranularity::Weekly,
            'report_date' => today(),
            'payload' => [],
        ]);

        return view('periodic.form', [
            'reportType' => $reportType,
            'report' => $report,
            'granularities' => PeriodGranularity::options(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->authorize('create', PeriodicReport::class);
        $reportType = PeriodicReportType::from($type);
        $user = $request->user();

        $validated = $this->validated($request, $reportType);
        $reportDate = Carbon::createFromFormat('Y-m-d', $validated['report_date']);

        $report = PeriodicReport::create([
            'report_type' => $reportType,
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

            return redirect()->route('periodic.show', $report)->with('status', 'ሪፖርቱ ለወረዳ ተልኳል።');
        }

        return redirect()->route('periodic.show', $report)->with('status', 'ረቂቁ ተቀምጧል።');
    }

    public function show(PeriodicReport $periodicReport)
    {
        $this->authorize('view', $periodicReport);
        $periodicReport->load(['user', 'location.parent', 'actions.user', 'verifier', 'approver']);

        return view('periodic.show', [
            'report' => $periodicReport,
            'reportType' => $periodicReport->report_type,
        ]);
    }

    public function edit(PeriodicReport $periodicReport)
    {
        $this->authorize('update', $periodicReport);

        return view('periodic.form', [
            'reportType' => $periodicReport->report_type,
            'report' => $periodicReport,
            'granularities' => PeriodGranularity::options(),
        ]);
    }

    public function update(Request $request, PeriodicReport $periodicReport): RedirectResponse
    {
        $this->authorize('update', $periodicReport);
        $validated = $this->validated($request, $periodicReport->report_type);
        $reportDate = Carbon::createFromFormat('Y-m-d', $validated['report_date']);

        $periodicReport->update([
            'period_type' => $validated['period_type'],
            'report_date' => $reportDate,
            'fiscal_year' => EthiopianDate::fiscalYear($reportDate),
            'payload' => $validated['payload'],
            'notes' => $validated['notes'],
        ]);

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($periodicReport, $request->user());

            return redirect()->route('periodic.show', $periodicReport)->with('status', 'ሪፖርቱ ለወረዳ ተልኳል።');
        }

        return redirect()->route('periodic.show', $periodicReport)->with('status', 'ለውጡ ተቀምጧል።');
    }

    public function destroy(PeriodicReport $periodicReport): RedirectResponse
    {
        $this->authorize('delete', $periodicReport);
        $type = $periodicReport->report_type->value;
        $periodicReport->delete();

        return redirect()->route('periodic.index', $type)->with('status', 'ረቂቁ ተሰርዟል።');
    }

    public function submit(Request $request, PeriodicReport $periodicReport): RedirectResponse
    {
        $this->authorize('submit', $periodicReport);
        $this->workflow->submit($periodicReport, $request->user());

        return redirect()->route('periodic.show', $periodicReport)->with('status', 'ሪፖርቱ ለወረዳ ተልኳል።');
    }

    public function verify(Request $request, PeriodicReport $periodicReport): RedirectResponse
    {
        $this->authorize('verify', $periodicReport);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
        $this->workflow->verify($periodicReport, $request->user(), $note);

        return redirect()->route('periodic.show', $periodicReport)->with('status', 'ሪፖርቱ በወረዳ ተረጋግጦ ወደ ዞን ተላልፏል።');
    }

    public function approve(Request $request, PeriodicReport $periodicReport): RedirectResponse
    {
        $this->authorize('approve', $periodicReport);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
        $this->workflow->approve($periodicReport, $request->user(), $note);

        return redirect()->route('periodic.show', $periodicReport)->with('status', 'ሪፖርቱ በዞን ጸድቋል።');
    }

    public function return(Request $request, PeriodicReport $periodicReport): RedirectResponse
    {
        $this->authorize('return', $periodicReport);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->workflow->return($periodicReport, $request->user(), $data['reason']);

        return redirect()->route('periodic.show', $periodicReport)->with('status', 'ሪፖርቱ ወደ ቀበሌ ተመልሷል።');
    }

    /** @return array{report_date:string,period_type:string,payload:array<string,mixed>,notes:?string} */
    private function validated(Request $request, PeriodicReportType $type): array
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

        $fields = $type->fields();
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

        $validator = validator($request->all(), $rules, [], $attributes);

        $validator->after(function (Validator $v) use ($request, $type) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $p = (array) $request->input('payload', []);

            if ($type === PeriodicReportType::FarmerCounts) {
                $hh = (int) ($p['households_total'] ?? 0);
                if (((int) ($p['sowed_farmers'] ?? 0) + (int) ($p['unsowed_farmers'] ?? 0)) !== $hh) {
                    $v->errors()->add('payload.sowed_farmers', 'ዘር የዘሩ እና ያልዘሩ አርሶ አደሮች ድምር ከጠቅላላ ቤተሰብ ጋር እኩል መሆን አለበት።');
                }
                if ((int) ($p['female_farmers'] ?? 0) > $hh) {
                    $v->errors()->add('payload.female_farmers', 'ሴት አርሶ አደሮች ከጠቅላላ ቤተሰብ መብለጥ አይችሉም።');
                }
                if ((int) ($p['youth_farmers'] ?? 0) > $hh) {
                    $v->errors()->add('payload.youth_farmers', 'ወጣት አርሶ አደሮች ከጠቅላላ ቤተሰብ መብለጥ አይችሉም።');
                }
            } elseif ($type === PeriodicReportType::FertilizerWarehouse) {
                $stock = (float) ($p['stock_quintal'] ?? 0);
                $sum = (float) ($p['distributed_quintal'] ?? 0) + (float) ($p['remaining_quintal'] ?? 0);
                if (abs($sum - $stock) > 0.01) {
                    $v->errors()->add('payload.remaining_quintal', 'የተከፋፈለ እና የተረፈ ማዳበሪያ ድምር ከክምችቱ ጋር እኩል መሆን አለበት።');
                }
            } elseif ($type === PeriodicReportType::AgriStaff) {
                $total = (int) ($p['degree_count'] ?? 0) + (int) ($p['diploma_count'] ?? 0) + (int) ($p['other_count'] ?? 0);
                if ((int) ($p['female_staff'] ?? 0) > $total) {
                    $v->errors()->add('payload.female_staff', 'ሴት ሰራተኞች ከጠቅላላ ሰራተኞች ብዛት መብለጥ አይችሉም።');
                }
            } elseif ($type === PeriodicReportType::FarmerTraining) {
                $total = (int) ($p['trainees_total'] ?? 0);
                if ((int) ($p['female_trainees'] ?? 0) > $total) {
                    $v->errors()->add('payload.female_trainees', 'ሴት ሰልጣኞች ከጠቅላላ ሰልጣኞች መብለጥ አይችሉም።');
                }
                if ((int) ($p['youth_trainees'] ?? 0) > $total) {
                    $v->errors()->add('payload.youth_trainees', 'ወጣት ሰልጣኞች ከጠቅላላ ሰልጣኞች መብለጥ አይችሉም።');
                }
            }
        });

        $data = $validator->validate();
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

        if ($type === PeriodicReportType::CropArea) {
            $payload['total_ha'] = round($payload['maize_ha'] + $payload['wheat_ha'] + $payload['barley_ha'] + $payload['other_ha'], 3);
        } elseif ($type === PeriodicReportType::AgriStaff) {
            $payload['total_staff'] = $payload['degree_count'] + $payload['diploma_count'] + $payload['other_count'];
        } elseif ($type === PeriodicReportType::SeedSupply) {
            $payload['total_quintal'] = round($payload['maize_quintal'] + $payload['wheat_quintal'] + $payload['barley_quintal'] + $payload['other_quintal'], 3);
        }

        return [
            'report_date' => $data['report_date'],
            'period_type' => $data['period_type'],
            'payload' => $payload,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
