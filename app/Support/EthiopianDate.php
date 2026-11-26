<?php

namespace App\Support;

use Carbon\CarbonInterface;

class EthiopianDate
{
    public const MONTHS = [
        1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ህዳር', 4 => 'ታህሳስ', 5 => 'ጥር', 6 => 'የካቲት',
        7 => 'መጋቢት', 8 => 'ሚያዚያ', 9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜን',
    ];

    /** @return array{0:int,1:int,2:int} [year, month, day] */
    public static function fromGregorian(int $year, int $month, int $day): array
    {
        $jdn = self::gregorianToJdn($year, $month, $day);
        $r = ($jdn - 1723856) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);
        $ey = 4 * intdiv($jdn - 1723856, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $em = intdiv($n, 30) + 1;
        $ed = ($n % 30) + 1;

        return [$ey, $em, $ed];
    }

    public static function format(?CarbonInterface $date): string
    {
        if (! $date) {
            return '';
        }

        [$y, $m, $d] = self::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));

        return sprintf('%s %d, %d', self::MONTHS[$m], $d, $y);
    }

    private static function gregorianToJdn(int $y, int $m, int $d): int
    {
        $a = intdiv(14 - $m, 12);
        $yy = $y + 4800 - $a;
        $mm = $m + 12 * $a - 3;

        return $d + intdiv(153 * $mm + 2, 5) + 365 * $yy + intdiv($yy, 4) - intdiv($yy, 100) + intdiv($yy, 400) - 32045;
    }
}
