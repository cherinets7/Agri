<?php

use App\Support\EthiopianDate;
use Carbon\CarbonInterface;

if (! function_exists('et_date')) {
    function et_date(?CarbonInterface $date): string
    {
        return EthiopianDate::format($date);
    }
}

if (! function_exists('qty')) {
    /** Plan/actual quantity: thousands separators, up to 3 decimals, no trailing zeros. */
    function qty(float|int|string|null $value, string $empty = '—'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        $formatted = number_format((float) $value, 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
