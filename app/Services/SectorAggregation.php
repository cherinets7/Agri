<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\SectorType;
use App\Models\Location;
use App\Models\SectorReport;
use App\Models\User;
use Carbon\CarbonInterface;

class SectorAggregation
{
    public function summarize(
        User $viewer,
        SectorType $sector,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $woredaId = null,
        bool $approvedOnly = false,
    ): array {
        $fields = $sector->summableFields();
        $zeroes = array_fill_keys(array_keys($fields), 0.0);

        $reports = SectorReport::query()
            ->visibleTo($viewer)
            ->counted($approvedOnly)
            ->where('sector', $sector->value)
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

            $grouped = $kebeleRows->groupBy(fn ($r) => $r['woreda']?->id);
            $woredaRows = $woredaQuery->get()->map(function (Location $woreda) use ($grouped, $fields, $zeroes) {
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

        return [
            'fields' => $fields,
            'kebeleRows' => $kebeleRows,
            'woredaRows' => $woredaRows,
            'totals' => $totals,
            'reportCount' => $reports->count(),
        ];
    }

    public function pendingCounts(User $user): array
    {
        $base = SectorReport::query()->visibleTo($user);

        if ($user->isWoreda()) {
            return ['label' => 'ለማረጋገጥ የሚጠብቁ (የዘርፍ ቅጾች)', 'count' => (clone $base)->where('status', 'submitted')->count()];
        }

        if ($user->isZone()) {
            return ['label' => 'ለማጽደቅ የሚጠብቁ (የዘርፍ ቅጾች)', 'count' => (clone $base)->where('status', 'woreda_verified')->count()];
        }

        return ['label' => 'የተመለሱ (የዘርፍ ቅጾች)', 'count' => (clone $base)->where('status', 'returned')->count()];
    }
}
