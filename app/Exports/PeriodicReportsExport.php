<?php

namespace App\Exports;

use App\Enums\PeriodicReportType;
use App\Support\EthiopianDate;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class PeriodicReportsExport implements WithMultipleSheets
{
    public function __construct(
        private PeriodicReportType $type,
        private Builder $query,
        private array $summary,
    ) {}

    public function sheets(): array
    {
        return [
            new PeriodicReportsDetailSheet($this->type, $this->query),
            new PeriodicReportsSummarySheet($this->type, $this->summary),
        ];
    }
}

class PeriodicReportsDetailSheet implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private PeriodicReportType $type,
        private Builder $query,
    ) {}

    public function title(): string
    {
        return 'ዝርዝር ሪፖርቶች';
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        $base = ['ቀን (ግሪጎሪያን)', 'ቀን (ኢትዮጵያ)', 'ወቅት/ድግግሞሽ', 'ወረዳ', 'ቀበሌ', 'ባለሙያ', 'ሁኔታ'];
        foreach ($this->type->fields() as $field) {
            $base[] = $field['label'];
        }
        $base[] = 'ማስታወሻ';

        return $base;
    }

    public function map($report): array
    {
        $woreda = $report->location?->parent;
        $row = [
            $report->report_date->toDateString(),
            EthiopianDate::format($report->report_date),
            $report->period_type->label(),
            $woreda?->name_am,
            $report->location?->name_am,
            $report->user?->name,
            $report->status->label(),
        ];

        foreach ($this->type->fields() as $key => $field) {
            $val = $report->payload[$key] ?? null;
            if ($field['type'] === 'select' && $val !== null) {
                $val = $field['options'][$val] ?? $val;
            }
            $row[] = $val;
        }

        $row[] = $report->notes;

        return $row;
    }
}

class PeriodicReportsSummarySheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(
        private PeriodicReportType $type,
        private array $summary,
    ) {}

    public function title(): string
    {
        return 'ማጠቃለያ';
    }

    public function array(): array
    {
        $fields = $this->summary['fields'];
        $rows = [];

        if ($this->summary['woredaRows']->isNotEmpty()) {
            $head = ['ወረዳ', 'የሪፖርት ብዛት', 'የላኩ ቀበሌዎች'];
            foreach ($fields as $f) {
                $head[] = $f['label'];
            }
            $rows[] = $head;

            foreach ($this->summary['woredaRows'] as $r) {
                $line = [$r['woreda']->name_am, $r['reports'], $r['kebeles_reported']];
                foreach (array_keys($fields) as $key) {
                    $line[] = $r['sums'][$key] ?? 0;
                }
                $rows[] = $line;
            }

            $totalLine = ['ዞናዊ ድምር', $this->summary['reportCount'], $this->summary['kebeleRows']->count()];
            foreach (array_keys($fields) as $key) {
                $totalLine[] = $this->summary['totals'][$key] ?? 0;
            }
            $rows[] = $totalLine;
            $rows[] = [];
        }

        if ($this->summary['kebeleRows']->isNotEmpty()) {
            $kHead = ['ወረዳ', 'ቀበሌ', 'የሪፖርት ብዛት'];
            foreach ($fields as $f) {
                $kHead[] = $f['label'];
            }
            $rows[] = $kHead;

            foreach ($this->summary['kebeleRows'] as $r) {
                $line = [$r['woreda']?->name_am, $r['location']?->name_am, $r['reports']];
                foreach (array_keys($fields) as $key) {
                    $line[] = $r['sums'][$key] ?? 0;
                }
                $rows[] = $line;
            }
        }

        return $rows ?: [['ምንም የተጠናቀረ መረጃ የለም']];
    }
}
