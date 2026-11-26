<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\SectorType;
use App\Models\SectorReport;
use App\Services\SectorAggregation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class SectorReportsTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHierarchy();
    }

    public function test_kebele_creates_all_seven_sector_forms_with_computed_fields(): void
    {
        $date = today()->toDateString();

        $cases = [
            [SectorType::Irrigation, ['irrigation_source' => 'river', 'water_schemes' => 2, 'irrigated_ha' => 14.5, 'beneficiaries_male' => 20, 'beneficiaries_female' => 10], ['beneficiaries_total' => 30]],
            [SectorType::Belg, ['maize_ha' => 10, 'vegetables_ha' => 4.5, 'wheat_ha' => 5.5, 'expected_yield_quintal' => 180, 'moisture_status' => 'good'], ['total_ha' => 20.0]],
            [SectorType::Livestock, ['ai_inseminations' => 12, 'calves_born' => 8, 'vaccinated_count' => 150, 'milk_liters' => 420, 'forage_ha' => 6.5], []],
            [SectorType::Fishery, ['active_ponds' => 3, 'fingerlings_stocked' => 1200, 'fish_harvest_kg' => 85.5, 'beneficiary_farmers' => 15], []],
            [SectorType::NaturalResource, ['terracing_km' => 8.5, 'seedlings_planted' => 5000, 'watershed_ha' => 25, 'compost_tons' => 18, 'participants_count' => 120], []],
            [SectorType::CoffeeSpice, ['coffee_seedlings' => 4000, 'coffee_ha' => 12, 'berbere_ha' => 3.5, 'korarima_ha' => 2, 'other_spice_ha' => 1, 'rehabilitated_ha' => 4], []],
            [SectorType::DemoFattening, ['cattle_fattened' => 18, 'shoat_fattened' => 42, 'demo_plots_count' => 4, 'trial_participants' => 28, 'trial_outcome' => 'ጥሩ ውጤት ተገኝቷል'], []],
        ];

        foreach ($cases as [$sector, $payload, $expected]) {
            $this->actingAs($this->expertA)->post(route('sectors.store', $sector->value), [
                'report_date' => $date,
                'period_type' => 'weekly',
                'payload' => $payload,
                'action' => 'submit',
            ])->assertRedirect();

            $report = SectorReport::where('sector', $sector->value)->firstOrFail();
            $this->assertSame(ReportStatus::Submitted, $report->status);
            foreach ($expected as $k => $v) {
                $this->assertEquals($v, $report->payload[$k]);
            }
        }

        $this->assertSame(7, SectorReport::count());
    }

    public function test_sector_approval_scoping_aggregation_and_export(): void
    {
        $date = today();

        $this->actingAs($this->expertA)->post(route('sectors.store', 'belg'), [
            'report_date' => $date->toDateString(),
            'period_type' => 'seasonal',
            'payload' => ['maize_ha' => 10, 'vegetables_ha' => 5, 'wheat_ha' => 5, 'expected_yield_quintal' => 120, 'moisture_status' => 'good'],
            'action' => 'submit',
        ])->assertRedirect();

        $report = SectorReport::firstOrFail();

        $this->actingAs($this->woredaUserB)->post(route('sectors.verify', $report))->assertForbidden();
        $this->actingAs($this->woredaUserA)->post(route('sectors.verify', $report))->assertRedirect();
        $this->assertSame(ReportStatus::WoredaVerified, $report->fresh()->status);

        $this->actingAs($this->zoneUser)->post(route('sectors.approve', $report))->assertRedirect();
        $this->assertSame(ReportStatus::ZoneApproved, $report->fresh()->status);

        $summary = app(SectorAggregation::class)->summarize($this->zoneUser, SectorType::Belg, $date->copy()->subDay(), $date);
        $this->assertEquals(20.0, $summary['totals']['total_ha']);

        $this->actingAs($this->zoneUser)->get(route('sectors.hub'))->assertOk()->assertSee('የባለሙያ ተግባር ክፍሎች');
        $export = $this->actingAs($this->zoneUser)->get(route('exports.sectors', 'belg'));
        $export->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $export->headers->get('content-type'),
        );
    }
}
