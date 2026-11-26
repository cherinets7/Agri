<?php

namespace App\Exports;

use App\Support\EthiopianDate;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class AnnualReportExport implements WithMultipleSheets
{
    public function __construct(private array $data) {}

    public function sheets(): array
    {
        $sheets = [
            new AnnualOverviewSheet($this->data),
            new AnnualExtensionSheet($this->data),
            new AnnualPeriodicSheet($this->data),
            new AnnualSectorSheet($this->data),
        ];

        if ($this->data['matrix']) {
            $sheets[] = new AnnualMatrixSheet($this->data['matrix']);
        }

        return $sheets;
    }
}

class AnnualOverviewSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'አጠቃላይ ማጠቃለያ';
    }

    public function array(): array
    {
        $d = $this->data;

        return [
            ['የየም ዞን ግብርና መምሪያ — ዓመታዊ ሪፖርት', $d['fiscalYear'].' ዓ/ም'],
            ['ጊዜ', EthiopianDate::format($d['from']).' – '.EthiopianDate::format($d['to'])],
            [],
            ['ዋና አመልካቾች', 'ዋጋ'],
            ['የተገለገሉ አርሶ አደሮች ጠቅላላ', $d['totalFarmers']],
            ['ሪፖርት የተላከባቸው ቀናት', $d['totalReportedDays']],
            ['የአርሶ አደር አስተያየት ብዛት', $d['feedbackCount']],
            ['አማካይ የአርሶ አደር ደረጃ (ከ5)', $d['feedbackAvg'] ?? '—'],
            ['ሳምንታዊ የባለሙያ ማጠቃለያዎች', $d['weeklySummaryCount']],
        ];
    }
}

class AnnualExtensionSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'ኤክስቴንሽን አፈጻጸም';
    }

    public function array(): array
    {
        $rows = [['ባለሙያ', 'ቀበሌ', 'ወረዳ', 'ሪፖርት ቀናት', 'ጠቅላላ አርሶ አደር', 'ሴት', 'ወጣት', 'አማካይ/ቀን', 'ባንድ']];
        foreach ($this->data['expertRows'] as $r) {
            $rows[] = [
                $r['user']->name,
                $r['kebele']?->name_am,
                $r['woreda']?->name_am,
                $r['days_reported'],
                $r['farmers'],
                $r['female'],
                $r['youth'],
                $r['avg'] ?? '—',
                $r['band']->label(),
            ];
        }

        return $rows;
    }
}

class AnnualPeriodicSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'ሪፖርቶች #2–#8';
    }

    public function array(): array
    {
        $rows = [['ሪፖርት ዓይነት', 'የሪፖርት ብዛት', 'መስክ', 'ድምር']];
        foreach ($this->data['periodicSummaries'] as $entry) {
            $type = $entry['type'];
            $sum = $entry['summary'];
            foreach ($sum['fields'] as $key => $f) {
                $rows[] = ["#{$type->number()} {$type->label()}", $sum['reportCount'], $f['label'], $sum['totals'][$key] ?? 0];
            }
        }

        return $rows;
    }
}

class AnnualSectorSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'የዘርፍ ቅጾች';
    }

    public function array(): array
    {
        $rows = [['ዘርፍ', 'የሪፖርት ብዛት', 'መስክ', 'ድምር']];
        foreach ($this->data['sectorSummaries'] as $entry) {
            $sector = $entry['sector'];
            $sum = $entry['summary'];
            foreach ($sum['fields'] as $key => $f) {
                $rows[] = [$sector->label(), $sum['reportCount'], $f['label'], $sum['totals'][$key] ?? 0];
            }
        }

        return $rows;
    }
}

class AnnualMatrixSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function __construct(private array $matrix) {}

    public function title(): string
    {
        return 'ዕቅድና ክንውን';
    }

    public function array(): array
    {
        $rows = [['ግብ', 'ተግባር', 'መለኪያ', 'ዞናዊ ዕቅድ', 'ዞናዊ ክንውን', 'አፈጻጸም %']];
        foreach ($this->matrix['goals'] as $g) {
            foreach ($g['rows'] as $r) {
                $rows[] = [
                    $g['goal']->name_am,
                    $r['activity']->name_am,
                    $r['activity']->unit->label(),
                    $r['zone_plan'] ?? '—',
                    $r['zone_actual'] ?? '—',
                    $r['zone_pct'] !== null ? $r['zone_pct'].'%' : '—',
                ];
            }
        }

        return $rows;
    }
}
