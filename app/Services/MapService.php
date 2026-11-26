<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\PerformanceBand;
use App\Models\ExtensionReport;
use App\Models\Location;
use App\Models\User;
use App\Support\EthiopianDate;
use App\Support\Progress;
use Carbon\CarbonInterface;

class MapService
{
    public function __construct(
        private ExtensionPerformance $perf,
        private PlanMatrix $matrix,
    ) {}

    /**
     * Per-woreda GIS choropleth metrics scoped to viewer.
     *
     * @return array<string, array<string, mixed>> keyed by Location.code
     */
    public function woredaMetrics(User $viewer, CarbonInterface $from, CarbonInterface $to, ?int $fiscalYear = null): array
    {
        $fiscalYear = $fiscalYear ?: $this->matrix->defaultYear();
        $woredas = $viewer->isZone()
            ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get()
            : Location::query()->whereKey($viewer->location_id)->get();

        $expertRows = $this->perf->byExpert($viewer, $from, $to);
        $byWoreda = $this->perf->byWoreda($expertRows)->keyBy(fn ($r) => $r['woreda']?->id);

        $matrixData = $this->matrix->build($viewer, $fiscalYear);
        $matrixRows = collect($matrixData['goals'])->flatMap(fn ($g) => $g['rows']);

        $reportCounts = ExtensionReport::query()
            ->visibleTo($viewer)
            ->counted()
            ->between($from, $to)
            ->join('locations as k', 'k.id', '=', 'extension_reports.location_id')
            ->groupBy('k.parent_id')
            ->selectRaw('k.parent_id as woreda_id, COUNT(*) as cnt')
            ->pluck('cnt', 'woreda_id');

        $kebeleCounts = Location::query()
            ->ofLevel(Level::Kebele)
            ->whereIn('parent_id', $woredas->pluck('id'))
            ->groupBy('parent_id')
            ->selectRaw('parent_id, COUNT(*) as cnt')
            ->pluck('cnt', 'parent_id');

        $out = [];
        foreach ($woredas as $w) {
            $ext = $byWoreda->get($w->id);
            $band = $ext['band'] ?? PerformanceBand::None;

            $pctValues = $matrixRows
                ->map(fn ($r) => $r['cells'][$w->id]['pct'] ?? null)
                ->filter(fn ($v) => $v !== null);
            $matrixPct = $pctValues->isNotEmpty() ? round((float) $pctValues->avg(), 1) : null;

            $out[$w->code] = [
                'id' => $w->id,
                'code' => $w->code,
                'name_am' => $w->name_am,
                'kebeles' => (int) ($kebeleCounts[$w->id] ?? 0),
                'experts' => (int) ($ext['experts'] ?? 0),
                'reports' => (int) ($reportCounts[$w->id] ?? 0),
                'farmers' => (int) ($ext['farmers'] ?? 0),
                'avg' => $ext['avg'] ?? null,
                'band' => $band->value,
                'band_label' => $band->label(),
                'band_color' => match ($band) {
                    PerformanceBand::Low => '#c62828',
                    PerformanceBand::Acceptable => '#ef6c00',
                    PerformanceBand::Target => '#2e7d32',
                    PerformanceBand::Over => '#1565c0',
                    PerformanceBand::None => '#90a4ae',
                },
                'matrix_pct' => $matrixPct,
                'matrix_color' => match (Progress::badge($matrixPct)) {
                    'red' => '#c62828',
                    'amber' => '#ef6c00',
                    'green' => '#2e7d32',
                    'blue' => '#1565c0',
                    default => '#90a4ae',
                },
                'dashboard_url' => route('dashboard', ['woreda' => $w->id]),
            ];
        }

        return $out;
    }

    public function currentFiscalYear(): int
    {
        return EthiopianDate::fiscalYear(now());
    }
}
