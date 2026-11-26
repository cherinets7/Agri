<?php

namespace App\Enums;

enum Level: string
{
    case Zone = 'zone';
    case Woreda = 'woreda';
    case Kebele = 'kebele';

    public function label(): string
    {
        return match ($this) {
            self::Zone => 'ዞን',
            self::Woreda => 'ወረዳ',
            self::Kebele => 'ቀበሌ',
        };
    }

    public function parentLevel(): ?self
    {
        return match ($this) {
            self::Zone => null,
            self::Woreda => self::Zone,
            self::Kebele => self::Woreda,
        };
    }
}
