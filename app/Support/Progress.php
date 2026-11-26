<?php

namespace App\Support;

class Progress
{
    public static function percent(?float $actual, ?float $plan): ?float
    {
        if ($actual === null || $plan === null || $plan <= 0) {
            return null;
        }

        return round($actual / $plan * 100, 1);
    }

    public static function badge(?float $percent): string
    {
        return match (true) {
            $percent === null => 'gray',
            $percent < 50 => 'red',
            $percent < 80 => 'amber',
            $percent <= 100 => 'green',
            default => 'blue',
        };
    }
}
