<?php

namespace App\Enums;

enum PeriodGranularity: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Seasonal = 'seasonal';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'ዕለታዊ',
            self::Weekly => 'ሳምንታዊ',
            self::Monthly => 'ወርሃዊ',
            self::Seasonal => 'ወቅታዊ',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }
}
