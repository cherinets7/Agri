<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

    /** Gregorian date for an Ethiopian [year, month, day]. */
    public static function toGregorian(int $year, int $month, int $day): Carbon
    {
        $jdn = 1723856 + 365 + 365 * ($year - 1) + intdiv($year, 4) + 30 * $month + $day - 31;

        $l = $jdn + 68569;
        $n = intdiv(4 * $l, 146097);
        $l -= intdiv(146097 * $n + 3, 4);
        $i = intdiv(4000 * ($l + 1), 1461001);
        $l = $l - intdiv(1461 * $i, 4) + 31;
        $j = intdiv(80 * $l, 2447);
        $d = $l - intdiv(2447 * $j, 80);
        $l = intdiv($j, 11);
        $m = $j + 2 - 12 * $l;
        $y = 100 * ($n - 49) + $i + $l;

        return Carbon::create($y, $m, $d)->startOfDay();
    }

    /** Plan (budget) year a Gregorian date belongs to — see config/plan.php. */
    public static function fiscalYear(CarbonInterface $date): int
    {
        [$ey, $em] = self::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
        $start = (int) config('plan.fiscal_year_start_month', 11);

        return $start > 1 && $em >= $start ? $ey + 1 : $ey;
    }

    /** @return array{0:Carbon,1:Carbon} first and last day of a plan year */
    public static function fiscalYearRange(int $fiscalYear): array
    {
        $start = (int) config('plan.fiscal_year_start_month', 11);
        $from = self::toGregorian($start > 1 ? $fiscalYear - 1 : $fiscalYear, max($start, 1), 1);
        $nextFrom = self::toGregorian($start > 1 ? $fiscalYear : $fiscalYear + 1, max($start, 1), 1);

        return [$from, $nextFrom->subDay()];
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month < 13) {
            return 30;
        }

        return ($year % 4 === 3) ? 6 : 5;
    }

    /** @return array{0:Carbon,1:Carbon} */
    public static function monthRange(int $etYear, int $etMonth): array
    {
        $from = self::toGregorian($etYear, $etMonth, 1);
        $to = self::toGregorian($etYear, $etMonth, self::daysInMonth($etYear, $etMonth));

        return [$from, $to];
    }

    /**
     * Ethiopian fiscal quarters:
     * Q1: ሐምሌ–መስከረም (11,12,13,1 of fy-1..fy)
     * Q2: ጥቅምት–ታህሳስ (2,3,4 of fy)
     * Q3: ጥር–መጋቢት (5,6,7 of fy)
     * Q4: ሚያዚያ–ሰኔ (8,9,10 of fy)
     *
     * @return array{0:Carbon,1:Carbon,2:string} [from, to, label]
     */
    public static function fiscalQuarterRange(int $fiscalYear, int $quarter): array
    {
        return match ($quarter) {
            1 => [self::toGregorian($fiscalYear - 1, 11, 1), self::toGregorian($fiscalYear, 1, 30), '1ኛ ሩብ (ሐምሌ–መስከረም)'],
            2 => [self::toGregorian($fiscalYear, 2, 1), self::toGregorian($fiscalYear, 4, 30), '2ኛ ሩብ (ጥቅምት–ታህሳስ)'],
            3 => [self::toGregorian($fiscalYear, 5, 1), self::toGregorian($fiscalYear, 7, 30), '3ኛ ሩብ (ጥር–መጋቢት)'],
            default => [self::toGregorian($fiscalYear, 8, 1), self::toGregorian($fiscalYear, 10, 30), '4ኛ ሩብ (ሚያዚያ–ሰኔ)'],
        };
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
