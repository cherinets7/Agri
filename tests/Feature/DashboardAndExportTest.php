<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Exports\ReportsExport;
use App\Http\Controllers\ExtensionReportController;
use App\Models\ExtensionReport;
use App\Services\ExtensionPerformance;
use App\Services\ReportWorkflow;
use App\Support\Period;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class DashboardAndExportTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHierarchy();
    }

    private function seedReport($expert, int $total, int $daysAgo = 1, ReportStatus $status = ReportStatus::Submitted): ExtensionReport
    {
        return ExtensionReport::create([
            'user_id' => $expert->id,
            'location_id' => $expert->location_id,
            'report_date' => today()->subDays($daysAgo),
            'status' => $status,
            'farmers_total' => $total,
            'new_farmers' => $total,
            'duration_minutes' => 60,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('ግባ');
    }

    public function test_login_and_inactive_users(): void
    {
        $this->post('/login', ['email' => $this->expertA->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->expertA->update(['is_active' => false]);
        $this->post('/login', ['email' => $this->expertA->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_dashboards_render_for_every_role_with_amharic_ui(): void
    {
        $this->seedReport($this->expertA, 3);
        $this->seedReport($this->expertB, 12);

        $this->actingAs($this->expertA)->get('/dashboard')->assertOk()->assertSee('የቀበሌ ዳሽቦርድ');
        $this->actingAs($this->woredaUserA)->get('/dashboard')->assertOk()->assertSee('የወረዳ ዳሽቦርድ');
        $this->actingAs($this->zoneUser)->get('/dashboard')->assertOk()->assertSee('የዞን ዳሽቦርድ')->assertSee('የወረዳዎች አፈጻጸም');
    }

    public function test_alerts_and_scope_on_dashboards(): void
    {
        $this->seedReport($this->expertA, 3);
        $this->seedReport($this->expertB, 12);

        $woreda = $this->actingAs($this->woredaUserA)->get('/dashboard?range=last30');
        $woreda->assertSee($this->expertA->name)->assertDontSee($this->expertB->name);
        $woreda->assertSee('ዝቅተኛ — ማስጠንቀቂያ');

        $zone = $this->actingAs($this->zoneUser)->get('/dashboard?range=last30');
        $zone->assertSee($this->expertA->name)->assertSee($this->expertB->name)->assertSee('ከመጠን በላይ — ማረጋገጥ ይገባል');
    }

    public function test_rows_use_per_reported_day_average_and_ignore_drafts(): void
    {
        $this->seedReport($this->expertA, 4, 1);
        $this->seedReport($this->expertA, 8, 2);
        $this->seedReport($this->expertA, 100, 3, ReportStatus::Draft);

        $rows = app(ExtensionPerformance::class)->byExpert($this->woredaUserA, today()->subDays(10), today());
        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['days_reported']);
        $this->assertSame(12, $rows[0]['farmers']);
        $this->assertSame(6.0, $rows[0]['avg']);
    }

    public function test_missing_report_detection(): void
    {
        $day = ExtensionPerformance::lastWorkDay();
        ExtensionReport::create([
            'user_id' => $this->expertA->id, 'location_id' => $this->expertA->location_id,
            'report_date' => $day, 'status' => ReportStatus::Submitted, 'farmers_total' => 6,
        ]);

        $missing = app(ExtensionPerformance::class)->missingOn($this->zoneUser, $day);
        $this->assertSame([$this->expertB->id], $missing->pluck('id')->all());
    }

    public function test_excel_exports_download_and_are_scoped(): void
    {
        $this->seedReport($this->expertA, 6);
        $this->seedReport($this->expertB, 7);

        $reports = $this->actingAs($this->woredaUserA)->get('/exports/reports');
        $reports->assertOk();
        $this->assertStringContainsString('spreadsheetml', $reports->headers->get('content-type'));
        $this->assertStringStartsWith('PK', $reports->baseResponse->getFile()->getContent());

        $this->actingAs($this->zoneUser)->get('/exports/performance')->assertOk();
    }

    public function test_reports_export_contains_only_visible_rows(): void
    {
        $this->seedReport($this->expertA, 6);
        $this->seedReport($this->expertB, 7);

        $export = new ReportsExport(ExtensionReportController::filteredQuery(
            request()->setUserResolver(fn () => $this->woredaUserA),
            Period::fromRequest(request(), 'last30'),
        ));
        $this->assertSame(1, $export->query()->count());
    }

    public function test_workflow_service_records_actions(): void
    {
        $report = $this->seedReport($this->expertA, 6, 1, ReportStatus::Draft);
        app(ReportWorkflow::class)->submit($report, $this->expertA);
        $this->assertSame(1, $report->actions()->count());
    }
}
