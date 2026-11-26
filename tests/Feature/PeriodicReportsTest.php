<?php

namespace Tests\Feature;

use App\Enums\PeriodicReportType;
use App\Enums\ReportStatus;
use App\Models\InputDispatch;
use App\Models\PeriodicReport;
use App\Services\PeriodicAggregation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class PeriodicReportsTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHierarchy();
    }

    public function test_kebele_creates_all_seven_report_types_with_computed_totals(): void
    {
        $date = today()->toDateString();

        $cases = [
            [PeriodicReportType::CropArea, ['season' => 'meher', 'maize_ha' => 10.5, 'wheat_ha' => 5.25, 'barley_ha' => 4.25, 'other_ha' => 1], ['total_ha' => 21.0]],
            [PeriodicReportType::FarmerCounts, ['households_total' => 100, 'sowed_farmers' => 80, 'unsowed_farmers' => 20, 'female_farmers' => 30, 'youth_farmers' => 40], []],
            [PeriodicReportType::FertilizerWarehouse, ['fertilizer_type' => 'nps', 'stock_quintal' => 50, 'distributed_quintal' => 35.5, 'remaining_quintal' => 14.5], []],
            [PeriodicReportType::AgriStaff, ['sector' => 'crop', 'degree_count' => 2, 'diploma_count' => 3, 'other_count' => 1, 'female_staff' => 2], ['total_staff' => 6]],
            [PeriodicReportType::FarmerTraining, ['ftc_count' => 1, 'sessions_count' => 3, 'trainees_total' => 60, 'female_trainees' => 20, 'youth_trainees' => 25, 'topic' => 'ዘር'], []],
            [PeriodicReportType::ZoneInputs, ['input_type' => 'fertilizer', 'unit' => 'quintal', 'woreda_dispatched' => 40, 'kebele_received' => 38, 'farmer_distributed' => 35, 'beneficiary_farmers' => 42], []],
            [PeriodicReportType::SeedSupply, ['maize_quintal' => 12, 'wheat_quintal' => 8, 'barley_quintal' => 5, 'other_quintal' => 2, 'beneficiary_farmers' => 50], ['total_quintal' => 27.0]],
        ];

        foreach ($cases as [$type, $payload, $expected]) {
            $res = $this->actingAs($this->expertA)->post(route('periodic.store', $type->value), [
                'report_date' => $date,
                'period_type' => 'weekly',
                'payload' => $payload,
                'action' => 'submit',
            ]);
            $res->assertRedirect();

            $report = PeriodicReport::where('report_type', $type->value)->firstOrFail();
            $this->assertSame(ReportStatus::Submitted, $report->status);
            foreach ($expected as $k => $v) {
                $this->assertEquals($v, $report->payload[$k]);
            }
        }

        $this->assertSame(7, PeriodicReport::count());
    }

    public function test_validation_rules_for_reports_3_4_5_and_6(): void
    {
        $date = today()->toDateString();

        // #3 sowed + unsowed != households_total
        $this->actingAs($this->expertA)->post(route('periodic.store', 'farmer_counts'), [
            'report_date' => $date,
            'period_type' => 'weekly',
            'payload' => ['households_total' => 100, 'sowed_farmers' => 50, 'unsowed_farmers' => 40, 'female_farmers' => 10, 'youth_farmers' => 10],
        ])->assertSessionHasErrors('payload.sowed_farmers');

        // #4 distributed + remaining != stock
        $this->actingAs($this->expertA)->post(route('periodic.store', 'fertilizer_warehouse'), [
            'report_date' => $date,
            'period_type' => 'weekly',
            'payload' => ['fertilizer_type' => 'urea', 'stock_quintal' => 100, 'distributed_quintal' => 60, 'remaining_quintal' => 30],
        ])->assertSessionHasErrors('payload.remaining_quintal');

        // #5 female_staff > total
        $this->actingAs($this->expertA)->post(route('periodic.store', 'agri_staff'), [
            'report_date' => $date,
            'period_type' => 'weekly',
            'payload' => ['sector' => 'all', 'degree_count' => 1, 'diploma_count' => 1, 'other_count' => 0, 'female_staff' => 5],
        ])->assertSessionHasErrors('payload.female_staff');

        // #6 youth_trainees > trainees_total
        $this->actingAs($this->expertA)->post(route('periodic.store', 'farmer_training'), [
            'report_date' => $date,
            'period_type' => 'weekly',
            'payload' => ['ftc_count' => 1, 'sessions_count' => 1, 'trainees_total' => 20, 'female_trainees' => 5, 'youth_trainees' => 25],
        ])->assertSessionHasErrors('payload.youth_trainees');
    }

    public function test_full_approval_workflow_and_scoping(): void
    {
        $this->actingAs($this->expertA)->post(route('periodic.store', 'crop_area'), [
            'report_date' => today()->toDateString(),
            'period_type' => 'weekly',
            'payload' => ['season' => 'meher', 'maize_ha' => 15, 'wheat_ha' => 10, 'barley_ha' => 5, 'other_ha' => 0],
            'action' => 'submit',
        ]);
        $report = PeriodicReport::firstOrFail();

        // Other woreda and other kebele cannot see or verify
        $this->actingAs($this->expertB)->get(route('periodic.show', $report))->assertForbidden();
        $this->actingAs($this->woredaUserB)->get(route('periodic.show', $report))->assertForbidden();
        $this->actingAs($this->woredaUserB)->post(route('periodic.verify', $report))->assertForbidden();

        // Woreda A cannot create kebele report
        $this->actingAs($this->woredaUserA)->get(route('periodic.create', 'crop_area'))->assertForbidden();

        // Woreda A verifies -> Zone approves -> action history recorded
        $this->actingAs($this->woredaUserA)->post(route('periodic.verify', $report), ['note' => 'ትክክል ነው'])->assertRedirect();
        $this->assertSame(ReportStatus::WoredaVerified, $report->fresh()->status);

        $this->actingAs($this->zoneUser)->post(route('periodic.approve', $report))->assertRedirect();
        $this->assertSame(ReportStatus::ZoneApproved, $report->fresh()->status);
        $this->assertSame(4, $report->actions()->count());

        // Zone return flow requires reason and unlocks for kebele edit
        $this->actingAs($this->expertA)->post(route('periodic.store', 'seed_supply'), [
            'report_date' => today()->toDateString(),
            'period_type' => 'weekly',
            'payload' => ['maize_quintal' => 5, 'wheat_quintal' => 2, 'barley_quintal' => 1, 'other_quintal' => 0, 'beneficiary_farmers' => 10],
            'action' => 'submit',
        ]);
        $seedReport = PeriodicReport::where('report_type', 'seed_supply')->firstOrFail();

        $this->actingAs($this->woredaUserA)->post(route('periodic.return', $seedReport), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->woredaUserA)->post(route('periodic.return', $seedReport), ['reason' => 'ዘር መጠን ያስተካክሉ'])->assertRedirect();
        $this->assertSame(ReportStatus::Returned, $seedReport->fresh()->status);

        $this->actingAs($this->expertA)->put(route('periodic.update', $seedReport), [
            'report_date' => today()->toDateString(),
            'period_type' => 'weekly',
            'payload' => ['maize_quintal' => 6, 'wheat_quintal' => 2, 'barley_quintal' => 1, 'other_quintal' => 0, 'beneficiary_farmers' => 12],
            'action' => 'submit',
        ])->assertRedirect();
        $this->assertSame(ReportStatus::Submitted, $seedReport->fresh()->status);
    }

    public function test_aggregation_and_zone_input_cascade_and_excel_export(): void
    {
        $date = today();

        // Expert A submitted (counted) + Draft (ignored)
        PeriodicReport::create([
            'report_type' => PeriodicReportType::ZoneInputs,
            'period_type' => 'weekly',
            'report_date' => $date,
            'fiscal_year' => 2018,
            'user_id' => $this->expertA->id,
            'location_id' => $this->kebeleA1->id,
            'status' => ReportStatus::Submitted,
            'payload' => ['input_type' => 'fertilizer', 'unit' => 'quintal', 'woreda_dispatched' => 50, 'kebele_received' => 48, 'farmer_distributed' => 45, 'beneficiary_farmers' => 60],
        ]);
        PeriodicReport::create([
            'report_type' => PeriodicReportType::ZoneInputs,
            'period_type' => 'weekly',
            'report_date' => $date,
            'fiscal_year' => 2018,
            'user_id' => $this->expertA->id,
            'location_id' => $this->kebeleA1->id,
            'status' => ReportStatus::Draft,
            'payload' => ['input_type' => 'fertilizer', 'unit' => 'quintal', 'woreda_dispatched' => 999, 'kebele_received' => 999, 'farmer_distributed' => 999, 'beneficiary_farmers' => 999],
        ]);
        PeriodicReport::create([
            'report_type' => PeriodicReportType::ZoneInputs,
            'period_type' => 'weekly',
            'report_date' => $date,
            'fiscal_year' => 2018,
            'user_id' => $this->expertB->id,
            'location_id' => $this->kebeleB1->id,
            'status' => ReportStatus::WoredaVerified,
            'payload' => ['input_type' => 'fertilizer', 'unit' => 'quintal', 'woreda_dispatched' => 30, 'kebele_received' => 30, 'farmer_distributed' => 25, 'beneficiary_farmers' => 35],
        ]);

        // Zone dispatches to Woreda A
        $this->actingAs($this->zoneUser)->post(route('dispatches.store'), [
            'to_location_id' => $this->woredaA->id,
            'dispatch_date' => $date->toDateString(),
            'input_type' => 'fertilizer',
            'unit' => 'quintal',
            'quantity' => 100,
        ])->assertRedirect();
        $this->assertSame(1, InputDispatch::count());

        $agg = app(PeriodicAggregation::class);
        $zoneSummary = $agg->summarize($this->zoneUser, PeriodicReportType::ZoneInputs, $date->copy()->subDay(), $date);
        $this->assertEquals(78.0, $zoneSummary['totals']['kebele_received']);
        $this->assertEquals(70.0, $zoneSummary['totals']['farmer_distributed']);
        $this->assertEquals(100.0, $zoneSummary['cascade']['totals']['zone_dispatched']);

        // Woreda A sees only Woreda A totals
        $woredaSummary = $agg->summarize($this->woredaUserA, PeriodicReportType::ZoneInputs, $date->copy()->subDay(), $date);
        $this->assertEquals(48.0, $woredaSummary['totals']['kebele_received']);

        // Hub & index pages render in Amharic
        $this->actingAs($this->zoneUser)->get(route('periodic.hub'))->assertOk()->assertSee('የግብርና ሪፖርቶች (#2–#8)');
        $this->actingAs($this->zoneUser)->get(route('periodic.index', 'zone_inputs'))->assertOk()->assertSee('ከዞን የተላከ');

        // Excel export downloads cleanly
        $export = $this->actingAs($this->zoneUser)->get(route('exports.periodic', 'zone_inputs'));
        $export->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $export->headers->get('content-type'),
        );
    }
}
