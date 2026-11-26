<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\PeriodicReportType;
use App\Models\ExtensionReport;
use App\Models\FarmerFeedback;
use App\Models\Location;
use App\Models\PeriodicReport;
use App\Models\User;
use App\Support\EthiopianDate;
use Carbon\CarbonInterface;

class ComparisonService
{
    public function __construct(
        private PlanMatrix $matrix,
        private PeriodicAggregation $periodic,
    ) {}

    public function compare(
        User $viewer,
        string $mode,
        int $fiscalYear,
        int $month = 1,
        int $quarter = 1,
        ?int $woredaId = null,
    ): array {
        [$curFrom, $curTo, $curLabel, $prevFrom, $prevTo, $prevLabel] = $this->resolvePeriods($mode, $fiscalYear, $month, $quarter);

        $curTotals = $this->snapshot($viewer, $curFrom, $curTo, $woredaId);
        $prevTotals = $this->snapshot($viewer, $prevFrom, $prevTo, $woredaId);

        $woredaRows = collect();
        if (! $viewer->isKebele()) {
            $wQuery = Location::query()->ofLevel(Level::Woreda)->orderBy('name_am');
            if ($viewer->isWoreda()) {
                $wQuery->whereKey($viewer->location_id);
            } elseif ($woredaId) {
                $wQuery->whereKey($woredaId);
            }
            $woredaRows = $wQuery->get()->map(function (Location $w) use ($viewer, $curFrom, $curTo, $prevFrom, $prevTo) {
                return [
                    'woreda' => $w,
                    'current' => $this->snapshot($viewer, $curFrom, $curTo, $w->id),
                    'previous' => $this->snapshot($viewer, $prevFrom, $prevTo, $w->id),
                ];
            });
        }

        // Plan vs Actual summary from PlanMatrix for the selected fiscal year (and previous year when mode === year)
        $planSummary = null;
        $prevPlanSummary = null;
        if (! $viewer->isKebele()) {
            $planSummary = $this->matrixStats($viewer, $fiscalYear, $curTo);
            if ($mode === 'year') {
                $prevPlanSummary = $this->matrixStats($viewer, $fiscalYear - 1, $prevTo);
            }
        }

        return [
            'mode' => $mode,
            'fiscalYear' => $fiscalYear,
            'month' => $month,
            'quarter' => $quarter,
            'currentLabel' => $curLabel,
            'previousLabel' => $prevLabel,
            'currentFrom' => $curFrom,
            'currentTo' => $curTo,
            'previousFrom' => $prevFrom,
            'previousTo' => $prevTo,
            'current' => $curTotals,
            'previous' => $prevTotals,
            'woredaRows' => $woredaRows,
            'planSummary' => $planSummary,
            'prevPlanSummary' => $prevPlanSummary,
        ];
    }

    private function resolvePeriods(string $mode, int $fiscalYear, int $month, int $quarter): array
    {
        if ($mode === 'month') {
            $etYear = $month >= 11 ? $fiscalYear - 1 : $fiscalYear;
            [$curFrom, $curTo] = EthiopianDate::monthRange($etYear, $month);
            $curLabel = EthiopianDate::MONTHS[$month]." {$etYear}";

            $prevMonth = $month === 1 ? 13 : $month - 1;
            $prevYear = $month === 1 ? $etYear - 1 : $etYear;
            [$prevFrom, $prevTo] = EthiopianDate::monthRange($prevYear, $prevMonth);
            $prevLabel = EthiopianDate::MONTHS[$prevMonth]." {$prevYear}";

            return [$curFrom, $curTo, $curLabel, $prevFrom, $prevTo, $prevLabel];
        }

        if ($mode === 'quarter') {
            [$curFrom, $curTo, $curLabel] = EthiopianDate::fiscalQuarterRange($fiscalYear, $quarter);
            $prevQ = $quarter === 1 ? 4 : $quarter - 1;
            $prevFy = $quarter === 1 ? $fiscalYear - 1 : $fiscalYear;
            [$prevFrom, $prevTo, $prevLabel] = EthiopianDate::fiscalQuarterRange($prevFy, $prevQ);

            return [$curFrom, $curTo, "{$curLabel} · {$fiscalYear}", $prevFrom, $prevTo, "{$prevLabel} · {$prevFy}"];
        }

        [$curFrom, $curTo] = EthiopianDate::fiscalYearRange($fiscalYear);
        [$prevFrom, $prevTo] = EthiopianDate::fiscalYearRange($fiscalYear - 1);

        return [$curFrom, $curTo, "{$fiscalYear} ዓ/ም", $prevFrom, $prevTo, ($fiscalYear - 1).' ዓ/ም'];
    }

    private function snapshot(User $viewer, CarbonInterface $from, CarbonInterface $to, ?int $woredaId): array
    {
        $extQuery = ExtensionReport::query()->visibleTo($viewer)->counted()->between($from, $to);
        $fbQuery = FarmerFeedback::query()->visibleTo($viewer)->between($from, $to);

        if ($woredaId && ! $viewer->isKebele()) {
            $kebeles = Location::query()->where('parent_id', $woredaId)->select('id');
            $extQuery->whereIn('location_id', $kebeles);
            $fbQuery->whereIn('location_id', $kebeles);
        }

        $ext = $extQuery->selectRaw('COUNT(*) as reports, COALESCE(SUM(farmers_total),0) as farmers, COALESCE(SUM(female_farmers),0) as female, COALESCE(SUM(youth_farmers),0) as youth')->first();
        $reports = (int) ($ext->reports ?? 0);
        $farmers = (int) ($ext->farmers ?? 0);

        $fb = $fbQuery->selectRaw('COUNT(*) as cnt, AVG(rating) as avg_rating')->first();

        $crop = $this->sumPayloadField($viewer, PeriodicReportType::CropArea, 'total_ha', $from, $to, $woredaId);
        $fert = $this->sumPayloadField($viewer, PeriodicReportType::FertilizerWarehouse, 'distributed_quintal', $from, $to, $woredaId);
        $seed = $this->sumPayloadField($viewer, PeriodicReportType::SeedSupply, 'total_quintal', $from, $to, $woredaId);
        $trained = $this->sumPayloadField($viewer, PeriodicReportType::FarmerTraining, 'trainees_total', $from, $to, $woredaId);

        return [
            'reports' => $reports,
            'farmers' => $farmers,
            'female' => (int) ($ext->female ?? 0),
            'youth' => (int) ($ext->youth ?? 0),
            'avg_per_day' => $reports > 0 ? round($farmers / $reports, 1) : 0.0,
            'feedback_count' => (int) ($fb->cnt ?? 0),
            'feedback_avg' => $fb->avg_rating !== null ? round((float) $fb->avg_rating, 2) : null,
            'crop_ha' => $crop,
            'fertilizer_quintal' => $fert,
            'seed_quintal' => $seed,
            'trained_farmers' => $trained,
        ];
    }

    private function sumPayloadField(User $viewer, PeriodicReportType $type, string $key, CarbonInterface $from, CarbonInterface $to, ?int $woredaId): float
    {
        $rows = PeriodicReport::query()
            ->visibleTo($viewer)
            ->counted()
            ->where('report_type', $type->value)
            ->between($from, $to)
            ->when($woredaId && ! $viewer->isKebele(), fn ($q) => $q->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id')))
            ->get(['payload']);

        return round($rows->sum(fn ($r) => (float) ($r->payload[$key] ?? 0)), 2);
    }

    private function matrixStats(User $viewer, int $fiscalYear, CarbonInterface $through): array
    {
        $data = $this->matrix->build($viewer, $fiscalYear, $through);
        $rows = collect($data['goals'])->flatMap(fn ($g) => $g['rows']);
        $withPct = $rows->filter(fn ($r) => $r['zone_pct'] !== null || ($viewer->isWoreda() && ($r['cells'][$viewer->location_id]['pct'] ?? null) !== null));

        $avgPct = $withPct->isEmpty() ? null : round($withPct->avg(function ($r) use ($viewer) {
            return $viewer->isZone() ? $r['zone_pct'] : ($r['cells'][$viewer->location_id]['pct'] ?? 0);
        }), 1);

        return [
            'activities_count' => $rows->count(),
            'avg_pct' => $avgPct,
        ];
    }
}
