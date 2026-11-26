<?php

use App\Support\EthiopianDate;
use Carbon\CarbonInterface;

if (! function_exists('et_date')) {
    function et_date(?CarbonInterface $date): string
    {
        return EthiopianDate::format($date);
    }
}
