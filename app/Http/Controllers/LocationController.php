<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()->isZone() || $request->user()->isWoreda(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureManager($request);
        $user = $request->user();

        $zones = Location::query()->ofLevel(Level::Zone)->with(['children' => fn ($q) => $q->orderBy('name_am'), 'children.children' => fn ($q) => $q->orderBy('name_am')])->get();

        if ($user->isWoreda()) {
            $zones = collect([
                (object) ['name_am' => $user->location->zone()?->name_am, 'children' => collect([$user->location->load(['children' => fn ($q) => $q->orderBy('name_am')])])],
            ]);
        }

        $parents = $user->isZone()
            ? Location::query()->whereIn('level', [Level::Zone->value, Level::Woreda->value])->orderBy('level')->orderBy('name_am')->get()
            : collect([$user->location]);

        return view('locations.index', ['zones' => $zones, 'parents' => $parents]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureManager($request);
        $user = $request->user();

        $data = $request->validate([
            'parent_id' => ['required', 'exists:locations,id'],
            'name_am' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('locations', 'code')],
        ], [], ['parent_id' => 'የበላይ አካባቢ', 'name_am' => 'ስም (አማርኛ)', 'name_en' => 'ስም (እንግሊዝኛ)', 'code' => 'ኮድ']);

        $parent = Location::findOrFail($data['parent_id']);
        $level = match ($parent->level) {
            Level::Zone => Level::Woreda,
            Level::Woreda => Level::Kebele,
            default => abort(422, 'ከቀበሌ በታች አካባቢ መጨመር አይቻልም።'),
        };

        if ($user->isWoreda()) {
            abort_unless($parent->id === $user->location_id, 403);
        }

        Location::create([...$data, 'level' => $level]);

        return redirect()->route('locations.index')->with('status', $level->label().' ተጨምሯል።');
    }
}
