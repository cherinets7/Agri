<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PerformanceExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function headings(): array
    {
        return [
            'ወረዳ', 'ቀበሌ', 'ባለሙያ', 'ሪፖርት የተላከባቸው ቀናት', 'የሚጠበቁ የሥራ ቀናት', 'ያልተላኩ ቀናት',
            'ጠቅላላ አርሶ አደር', 'ሴት', 'ወጣት', 'አማካይ በቀን', 'ባንድ',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn ($r) => [
            $r['woreda']?->name_am,
            $r['kebele']?->name_am,
            $r['user']->name,
            $r['days_reported'],
            $r['expected_days'],
            $r['missing_days'],
            $r['farmers'],
            $r['female'],
            $r['youth'],
            $r['avg'],
            $r['band']->label(),
        ]);
    }
}
