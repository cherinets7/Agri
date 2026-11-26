<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\PeriodicReportType;
use App\Models\InputDispatch;
use App\Models\Location;
use App\Support\EthiopianDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class InputDispatchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isKebele(), 403);

        $targetLevel = $user->isZone() ? Level::Woreda : Level::Kebele;
        $targetIds = Location::query()
            ->ofLevel($targetLevel)
            ->when($user->isWoreda(), fn ($q) => $q->where('parent_id', $user->location_id))
            ->pluck('id')
            ->all();

        $data = $request->validate([
            'to_location_id' => ['required', Rule::in($targetIds)],
            'dispatch_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'input_type' => ['required', Rule::in(array_keys(PeriodicReportType::inputTypes()))],
            'unit' => ['required', Rule::in(['quintal', 'liter', 'count'])],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['dispatch_date']);

        InputDispatch::create([
            'from_location_id' => $user->location_id,
            'to_location_id' => $data['to_location_id'],
            'user_id' => $user->id,
            'dispatch_date' => $date,
            'fiscal_year' => EthiopianDate::fiscalYear($date),
            'input_type' => $data['input_type'],
            'unit' => $data['unit'],
            'quantity' => round((float) $data['quantity'], 3),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'የግብዓት ስርጭቱ ተመዝግቧል።');
    }

    public function destroy(Request $request, InputDispatch $dispatch): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isZone() || $dispatch->user_id === $user->id, 403);
        $dispatch->delete();

        return back()->with('status', 'የግብዓት ስርጭቱ ተሰርዟል።');
    }
}
