<?php

namespace Tests\Unit;

use App\Enums\PerformanceBand;
use App\Services\ExtensionPerformance;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PerformanceBandTest extends TestCase
{
    public function test_bands_follow_the_5_to_10_rule(): void
    {
        $this->assertSame(PerformanceBand::Low, ExtensionPerformance::band(0));
        $this->assertSame(PerformanceBand::Low, ExtensionPerformance::band(4));
        $this->assertSame(PerformanceBand::Low, ExtensionPerformance::band(4.9));
        $this->assertSame(PerformanceBand::Acceptable, ExtensionPerformance::band(5));
        $this->assertSame(PerformanceBand::Acceptable, ExtensionPerformance::band(7));
        $this->assertSame(PerformanceBand::Acceptable, ExtensionPerformance::band(7.5));
        $this->assertSame(PerformanceBand::Target, ExtensionPerformance::band(8));
        $this->assertSame(PerformanceBand::Target, ExtensionPerformance::band(10));
        $this->assertSame(PerformanceBand::Over, ExtensionPerformance::band(11));
        $this->assertSame(PerformanceBand::None, ExtensionPerformance::band(null));
    }

    public function test_last_work_day_skips_weekends(): void
    {
        $monday = Carbon::parse('2026-11-23');
        $this->assertSame('2026-11-20', ExtensionPerformance::lastWorkDay($monday)->toDateString());
        $this->assertSame('2026-11-23', ExtensionPerformance::lastWorkDay($monday->copy()->addDay())->toDateString());
    }
}
