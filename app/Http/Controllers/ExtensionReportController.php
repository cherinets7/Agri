<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\ReportStatus;
use App\Models\ExtensionReport;
use App\Models\Location;
use App\Services\ReportWorkflow;
use App\Support\Period;
use App\Support\Signature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExtensionReportController extends Controller
{
    public function __construct(private ReportWorkflow $workflow) {}

    public static function filteredQuery(Request $request, Period $period)
    {
        $user = $request->user();
        $query = ExtensionReport::query()
            ->visibleTo($user)
            ->with(['user', 'location.parent'])
            ->between($period->from, $period->to);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $woredaId = $user->isWoreda() ? $user->location_id : ($request->integer('woreda') ?: null);
        if ($woredaId && ! $user->isKebele()) {
            $query->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id'));
        }

        return $query->orderByDesc('report_date')->orderByDesc('id');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');

        $reports = self::filteredQuery($request, $period)->paginate(20)->withQueryString();

        return view('reports.index', [
            'reports' => $reports,
            'period' => $period,
            'statuses' => ReportStatus::options(),
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', ExtensionReport::class);

        $report = new ExtensionReport([
            'report_date' => today(),
            'service_types' => [],
            'techniques' => [],
        ]);

        return view('reports.form', ['report' => $report]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExtensionReport::class);
        $user = $request->user();

        $data = $this->validated($request, null);

        $report = ExtensionReport::create([
            ...$data,
            'user_id' => $user->id,
            'location_id' => $user->location_id,
            'status' => ReportStatus::Draft,
        ]);
        $this->workflow->record($report, $user, 'create', null, ReportStatus::Draft);

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($report, $user, $data['signature_data'] ?? null);

            return redirect()->route('reports.show', $report)->with('status', 'ሪፖርቱ ለወረዳ ተልኳል።');
        }

        return redirect()->route('reports.show', $report)->with('status', 'ረቂቁ ተቀምጧል።');
    }

    public function show(ExtensionReport $report)
    {
        $this->authorize('view', $report);
        $report->load(['user', 'location.parent', 'actions.user', 'verifier', 'approver']);

        return view('reports.show', ['report' => $report]);
    }

    public function edit(ExtensionReport $report)
    {
        $this->authorize('update', $report);

        return view('reports.form', ['report' => $report]);
    }

    public function update(Request $request, ExtensionReport $report): RedirectResponse
    {
        $this->authorize('update', $report);

        $report->update($this->validated($request, $report));

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($report, $request->user(), $report->signature_data);

            return redirect()->route('reports.show', $report)->with('status', 'ሪፖርቱ ለወረዳ ተልኳል።');
        }

        return redirect()->route('reports.show', $report)->with('status', 'ለውጡ ተቀምጧል።');
    }

    public function destroy(ExtensionReport $report): RedirectResponse
    {
        $this->authorize('delete', $report);
        $report->delete();

        return redirect()->route('reports.index')->with('status', 'ረቂቁ ተሰርዟል።');
    }

    public function submit(Request $request, ExtensionReport $report): RedirectResponse
    {
        $this->authorize('submit', $report);
        $this->workflow->submit($report, $request->user(), Signature::fromRequest($request));

        return redirect()->route('reports.show', $report)->with('status', 'ሪፖርቱ ለወረዳ ተልኳል።');
    }

    public function verify(Request $request, ExtensionReport $report): RedirectResponse
    {
        $this->authorize('verify', $report);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'signature_data' => Signature::rules(),
        ]);
        $this->workflow->verify($report, $request->user(), $data['note'] ?? null, $data['signature_data'] ?? null);

        return redirect()->route('reports.show', $report)->with('status', 'ሪፖርቱ በወረዳ ተረጋግጦ ወደ ዞን ተላልፏል።');
    }

    public function approve(Request $request, ExtensionReport $report): RedirectResponse
    {
        $this->authorize('approve', $report);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'signature_data' => Signature::rules(),
        ]);
        $this->workflow->approve($report, $request->user(), $data['note'] ?? null, $data['signature_data'] ?? null);

        return redirect()->route('reports.show', $report)->with('status', 'ሪፖርቱ በዞን ጸድቋል።');
    }

    public function return(Request $request, ExtensionReport $report): RedirectResponse
    {
        $this->authorize('return', $report);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->workflow->return($report, $request->user(), $data['reason']);

        return redirect()->route('reports.show', $report)->with('status', 'ሪፖርቱ ወደ ቀበሌ ተመልሷል።');
    }

    private function validated(Request $request, ?ExtensionReport $report): array
    {
        $user = $request->user();
        $services = array_keys(config('extension.service_types'));
        $techniques = array_keys(config('extension.techniques'));

        $validator = validator($request->all(), [
            'report_date' => [
                'required', 'date_format:Y-m-d', 'before_or_equal:today',
                Rule::unique('extension_reports', 'report_date')
                    ->where('user_id', $report?->user_id ?? $user->id)
                    ->ignore($report?->id),
            ],
            'farmers_total' => ['required', 'integer', 'min:0', 'max:1000'],
            'new_farmers' => ['required', 'integer', 'min:0'],
            'repeat_farmers' => ['required', 'integer', 'min:0'],
            'female_farmers' => ['required', 'integer', 'min:0'],
            'youth_farmers' => ['required', 'integer', 'min:0'],
            'service_types' => ['nullable', 'array'],
            'service_types.*' => [Rule::in($services)],
            'techniques' => ['nullable', 'array'],
            'techniques.*' => [Rule::in($techniques)],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_data' => Signature::rules(),
        ], [], [
            'report_date' => 'ቀን',
            'farmers_total' => 'ጠቅላላ አርሶ አደር',
            'new_farmers' => 'አዲስ አርሶ አደሮች',
            'repeat_farmers' => 'ተደጋጋሚ አርሶ አደሮች',
            'female_farmers' => 'ሴት አርሶ አደሮች',
            'youth_farmers' => 'ወጣት አርሶ አደሮች',
            'service_types' => 'የአገልግሎት ዓይነት',
            'techniques' => 'ቴክኒክ',
            'duration_minutes' => 'ቆይታ',
            'notes' => 'ማስታወሻ',
        ]);

        $validator->after(function (Validator $v) use ($request) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $total = (int) $request->input('farmers_total');
            if ((int) $request->input('new_farmers') + (int) $request->input('repeat_farmers') !== $total) {
                $v->errors()->add('new_farmers', 'አዲስ እና ተደጋጋሚ አርሶ አደሮች ድምር ከጠቅላላው ጋር እኩል መሆን አለበት።');
            }
            foreach (['female_farmers' => 'ሴት', 'youth_farmers' => 'ወጣት'] as $field => $label) {
                if ((int) $request->input($field) > $total) {
                    $v->errors()->add($field, "{$label} አርሶ አደሮች ከጠቅላላው መብለጥ አይችሉም።");
                }
            }
            if ($total > 0 && (int) $request->input('duration_minutes') === 0) {
                $v->errors()->add('duration_minutes', 'አርሶ አደሮች ከተገለገሉ የቆይታ ጊዜ ይግቡ።');
            }
        });

        $data = $validator->validate();
        $data['service_types'] = array_values($data['service_types'] ?? []);
        $data['techniques'] = array_values($data['techniques'] ?? []);

        return $data;
    }
}
