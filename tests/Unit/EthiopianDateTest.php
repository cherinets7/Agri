<?php

namespace Tests\Unit;

use App\Support\EthiopianDate;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class EthiopianDateTest extends TestCase
{
    public function test_converts_known_dates(): void
    {
        $this->assertSame([2016, 1, 1], EthiopianDate::fromGregorian(2023, 9, 12));
        $this->assertSame([2015, 13, 6], EthiopianDate::fromGregorian(2023, 9, 11));
        $this->assertSame([2017, 1, 1], EthiopianDate::fromGregorian(2024, 9, 11));
        $this->assertSame([2017, 4, 29], EthiopianDate::fromGregorian(2025, 1, 7));
        $this->assertSame([2017, 5, 1], EthiopianDate::fromGregorian(2025, 1, 9));
    }

    public function test_formats_in_amharic(): void
    {
        $this->assertSame('መስከረም 1, 2016', EthiopianDate::format(Carbon::create(2023, 9, 12)));
        $this->assertSame('', EthiopianDate::format(null));
    }
}
