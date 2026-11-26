<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case WoredaVerified = 'woreda_verified';
    case ZoneApproved = 'zone_approved';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'ረቂቅ',
            self::Submitted => 'ተልኳል (ወረዳ ይጠብቃል)',
            self::WoredaVerified => 'በወረዳ ተረጋግጧል (ዞን ይጠብቃል)',
            self::ZoneApproved => 'በዞን ጸድቋል',
            self::Returned => 'ተመልሷል',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'amber',
            self::WoredaVerified => 'blue',
            self::ZoneApproved => 'green',
            self::Returned => 'red',
        };
    }

    /** @return list<string> */
    public static function counted(): array
    {
        return [self::Submitted->value, self::WoredaVerified->value, self::ZoneApproved->value];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
