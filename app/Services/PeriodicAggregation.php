<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\PeriodicReportType;
use App\Models\InputDispatch;
use App\Models\Location;
use App\Models\PeriodicReport;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PeriodicAggregation
{
    /**
     * Aggregate Reports #2-#8 across Kebele -> Woreda -> Zone for a period.
     *
     * @return array{
     *   fields: array<string, array<string, mixed>>,
     *   kebeleRows: Collection<int, array<string, mixed>>,
     *   woredaRows: Collection<int, array<string, mixed>>,
     *   totals: array<string, float>,
     *   reportCount: int,
     *   cascade: array<string, mixed>|null
     * }
     */
    public function summarize(
        User $viewer,
        PeriodicReportType $type,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $woredaId = null,
        bool $approvedOnly = false,
    ): array {
        $fields = $type->summableFields();
        $zeroes = array_fill_keys(array_keys($fields), 0.0);

        $reports = PeriodicReport::query()
            ->visibleTo($viewer)
            ->counted($approvedOnly)
            ->where('report_type', $type->value)
            ->between($from, $to)
            ->with('location.parent')
            ->when($woredaId && ! $viewer->isKebele(), function ($q) use ($woredaId) {
                $q->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id'));
            })
            ->get();

        $byKebele = [];
        foreach ($reports as $report) {
            $kid = $report->location_id;
            if (! isset($byKebele[$kid])) {
                $byKebele[$kid] = [
                    'location' => $report->location,
                    'woreda' => $report->location?->parent,
                    'reports' => 0,
                    'sums' => $zeroes,
                ];
            }
            $byKebele[$kid]['reports']++;
            foreach (array_keys($fields) as $key) {
                $byKebele[$kid]['sums'][$key] += (float) ($report->payload[$key] ?? 0);
            }
        }

        $kebeleRows = collect($byKebele)
            ->sortBy(fn ($r) => ($r['woreda']?->name_am ?? '').'|'.($r['location']?->name_am ?? ''))
            ->values();

        $woredaRows = collect();
        if ($viewer->isZone() || $viewer->isWoreda()) {
            $woredaQuery = Location::query()->ofLevel(Level::Woreda)->orderBy('name_am');
            if ($viewer->isWoreda()) {
                $woredaQuery->whereKey($viewer->location_id);
            } elseif ($woredaId) {
                $woredaQuery->whereKey($woredaId);
            }

            $woredas = $woredaQuery->get();
            $grouped = $kebeleRows->groupBy(fn ($r) => $r['woreda']?->id);

            $woredaRows = $woredas->map(function (Location $woreda) use ($grouped, $fields, $zeroes) {
                $group = $grouped->get($woreda->id, collect());
                $sums = $zeroes;
                foreach (array_keys($fields) as $key) {
                    $sums[$key] = round($group->sum(fn ($r) => $r['sums'][$key]), 3);
                }

                return [
                    'woreda' => $woreda,
                    'reports' => $group->sum('reports'),
                    'kebeles_reported' => $group->count(),
                    'sums' => $sums,
                ];
            });
        }

        $totals = $zeroes;
        foreach (array_keys($fields) as $key) {
            $totals[$key] = round($kebeleRows->sum(fn ($r) => $r['sums'][$key]), 3);
        }

        $cascade = null;
        if ($type === PeriodicReportType::ZoneInputs) {
            $cascade = $this->inputCascade($viewer, $from, $to, $woredaId, $woredaRows, $kebeleRows);
        }

        return [
            'fields' => $fields,
            'kebeleRows' => $kebeleRows,
            'woredaRows' => $woredaRows,
            'totals' => $totals,
            'reportCount' => $reports->count(),
            'cascade' => $cascade,
        ];
    }

    /** Build the 4-tier cascade summary (Zone -> Woreda -> Kebele -> Farmer) for Report #7. */
    private function inputCascade(
        User $viewer,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $woredaId,
        Collection $woredaRows,
        Collection $kebeleRows,
    ): array {
        $zoneId = Location::query()->ofLevel(Level::Zone)->value('id');

        $dispatches = InputDispatch::query()
            ->visibleTo($viewer)
            ->whereDate('dispatch_date', '>=', $from->toDateString())
            ->whereDate('dispatch_date', '<=', $to->toDateString())
            ->with(['fromLocation', 'toLocation'])
            ->get();

        $zoneToWoreda = $dispatches->where('from_location_id', $zoneId)->groupBy('to_location_id');
        $woredaToKebele = $dispatches->where('from_location_id', '!=', $zoneId);

        $rows = $woredaRows->map(function (array $wRow) use ($zoneToWoreda, $woredaToKebele) {
            $wid = $wRow['woreda']->id;
            $fromZone = $zoneToWoreda->get($wid, collect())->sum('quantity');
            $toKebeleDispatch = $woredaToKebele->where('from_location_id', $wid)->sum('quantity');
            $kebeleReportedWoredaSent = $wRow['sums']['woreda_dispatched'] ?? 0;

            return [
                'woreda' => $wRow['woreda'],
                'zone_dispatched' => round((float) $fromZone, 3),
                'woreda_dispatched' => round((float) max($toKebeleDispatch, $kebeleReportedWoredaSent), 3),
                'kebele_received' => $wRow['sums']['kebele_received'] ?? 0,
                'farmer_distributed' => $wRow['sums']['farmer_distributed'] ?? 0,
                'beneficiary_farmers' => $wRow['sums']['beneficiary_farmers'] ?? 0,
            ];
        });

        return [
            'woredaRows' => $rows,
            'dispatches' => $dispatches->sortByDesc('dispatch_date')->values(),
            'totals' => [
                'zone_dispatched' => round($rows->sum('zone_dispatched'), 3),
                'woreda_dispatched' => round($rows->sum('woreda_dispatched'), 3),
                'kebele_received' => round($rows->sum('kebele_received'), 3),
                'farmer_distributed' => round($rows->sum('farmer_distributed'), 3),
                'beneficiary_farmers' => round($rows->sum('beneficiary_farmers'), 3),
            ],
        ];
    }

    /** Count pending periodic reports awaiting verification/approval or returned for a user. */
    public function pendingCounts(User $user): array
    {
        $base = PeriodicReport::query()->visibleTo($user);

        if ($user->isWoreda()) {
            return [
                'label' => 'ለማረጋገጥ የሚጠብቁ (ሪፖርት #2–#8)',
                'status' => 'submitted',
                'count' => (clone $base)->where('status', 'submitted')->count(),
            ];
        }

        if ($user->isZone()) {
            return [
                'label' => 'ለማጽደቅ የሚጠብቁ (ሪፖርት #2–#8)',
                'status' => 'woreda_verified',
                'count' => (clone $base)->where('status', 'woreda_verified')->count(),
            ];
        }

        return [
            'label' => 'የተመለሱ (ሪፖርት #2–#8)',
            'status' => 'returned',
            'count' => (clone $base)->where('status', 'returned')->count(),
        ];
    }
}
