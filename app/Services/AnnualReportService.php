<?php

namespace App\Services;

use App\Enums\PeriodicReportType;
use App\Enums\SectorType;
use App\Models\ExpertWeeklySummary;
use App\Models\FarmerFeedback;
use App\Models\User;
use App\Support\EthiopianDate;

class AnnualReportService
{
    public function __construct(
        private ExtensionPerformance $performance,
        private PlanMatrix $matrix,
        private PeriodicAggregation $periodic,
        private SectorAggregation $sectors,
    ) {}

    public function compile(User $viewer, int $fiscalYear, ?int $woredaId = null): array
    {
        [$from, $to] = EthiopianDate::fiscalYearRange($fiscalYear);

        $expertRows = $this->performance->byExpert($viewer, $from, $to, $woredaId);
        $woredaExtension = $viewer->isZone() && ! $woredaId
            ? $this->performance->byWoreda($expertRows)
            : collect();

        $matrix = ($viewer->isZone() || $viewer->isWoreda())
            ? $this->matrix->build($viewer, $fiscalYear, $to)
            : null;

        $periodicSummaries = [];
        foreach (PeriodicReportType::cases() as $type) {
            $periodicSummaries[$type->value] = [
                'type' => $type,
                'summary' => $this->periodic->summarize($viewer, $type, $from, $to, $woredaId),
            ];
        }

        $sectorSummaries = [];
        foreach (SectorType::cases() as $sector) {
            $sectorSummaries[$sector->value] = [
                'sector' => $sector,
                'summary' => $this->sectors->summarize($viewer, $sector, $from, $to, $woredaId),
            ];
        }

        $fbStats = FarmerFeedback::query()
            ->visibleTo($viewer)
            ->between($from, $to)
            ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg_rating')
            ->first();

        $weeklySummaryCount = ExpertWeeklySummary::query()
            ->visibleTo($viewer)
            ->where('fiscal_year', $fiscalYear)
            ->count();

        return [
            'fiscalYear' => $fiscalYear,
            'from' => $from,
            'to' => $to,
            'expertRows' => $expertRows,
            'woredaExtension' => $woredaExtension,
            'totalFarmers' => $expertRows->sum('farmers'),
            'totalReportedDays' => $expertRows->sum('days_reported'),
            'matrix' => $matrix,
            'periodicSummaries' => $periodicSummaries,
            'sectorSummaries' => $sectorSummaries,
            'feedbackCount' => (int) ($fbStats->cnt ?? 0),
            'feedbackAvg' => $fbStats->avg_rating !== null ? round((float) $fbStats->avg_rating, 2) : null,
            'weeklySummaryCount' => $weeklySummaryCount,
        ];
    }
}
