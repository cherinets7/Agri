<?php

namespace App\Enums;

enum PlanUnit: string
{
    case Hectare = 'hectare';
    case Quintal = 'quintal';
    case Count = 'count';
    case Ton = 'ton';
    case Liter = 'liter';

    public function label(): string
    {
        return match ($this) {
            self::Hectare => 'ሄ/ር',
            self::Quintal => 'ኩ/ል',
            self::Count => 'ቁጥር',
            self::Ton => 'ቶን',
            self::Liter => 'ሊትር',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $u) => [$u->value => $u->label()])->all();
    }
}
