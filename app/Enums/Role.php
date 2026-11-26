<?php

namespace App\Enums;

enum Role: string
{
    case Kebele = 'kebele';
    case Woreda = 'woreda';
    case Zone = 'zone';

    public function label(): string
    {
        return match ($this) {
            self::Kebele => 'ቀበሌ ባለሙያ',
            self::Woreda => 'ወረዳ ኃላፊ',
            self::Zone => 'ዞን ኃላፊ',
        };
    }

    public function level(): Level
    {
        return match ($this) {
            self::Kebele => Level::Kebele,
            self::Woreda => Level::Woreda,
            self::Zone => Level::Zone,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
