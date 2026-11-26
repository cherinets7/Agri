<?php

namespace App\Exports;

use App\Support\EthiopianDate;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ቀን (ግሪጎሪያን)', 'ቀን (ኢትዮጵያ)', 'ዞን', 'ወረዳ', 'ቀበሌ', 'ባለሙያ', 'ሁኔታ',
            'ጠቅላላ አርሶ አደር', 'አዲስ', 'ተደጋጋሚ', 'ሴት', 'ወጣት (18–35)',
            'ቆይታ (ደቂቃ)', 'አገልግሎት ዓይነት', 'ቴክኒክ', 'ማስጠንቀቂያ ባንድ', 'ማስታወሻ',
        ];
    }

    public function map($report): array
    {
        $services = config('extension.service_types');
        $techniques = config('extension.techniques');
        $woreda = $report->location?->parent;

        return [
            $report->report_date->toDateString(),
            EthiopianDate::format($report->report_date),
            $woreda?->parent?->name_am,
            $woreda?->name_am,
            $report->location?->name_am,
            $report->user?->name,
            $report->status->label(),
            $report->farmers_total,
            $report->new_farmers,
            $report->repeat_farmers,
            $report->female_farmers,
            $report->youth_farmers,
            $report->duration_minutes,
            collect($report->service_types)->map(fn ($k) => $services[$k] ?? $k)->implode('፣ '),
            collect($report->techniques)->map(fn ($k) => $techniques[$k] ?? $k)->implode('፣ '),
            $report->band()->label(),
            $report->notes,
        ];
    }
}
