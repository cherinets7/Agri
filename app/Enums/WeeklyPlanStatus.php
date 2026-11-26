<?php

namespace App\Enums;

enum WeeklyPlanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'ረቂቅ',
            self::Submitted => 'ተልኳል (ዞን ይጠብቃል)',
            self::Approved => 'በዞን ጸድቋል',
            self::Returned => 'ተመልሷል',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'amber',
            self::Approved => 'green',
            self::Returned => 'red',
        };
    }

    /** Statuses whose numbers count towards the matrix ("ክንውን"). */
    public static function counted(): array
    {
        return [self::Submitted->value, self::Approved->value];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
