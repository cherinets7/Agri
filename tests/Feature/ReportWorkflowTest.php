<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\ExtensionReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHierarchy();
    }

    public function test_full_approval_chain(): void
    {
        $response = $this->actingAs($this->expertA)->post('/reports', $this->reportPayload(['action' => 'submit']));
        $report = ExtensionReport::firstOrFail();
        $response->assertRedirect(route('reports.show', $report));
        $this->assertSame(ReportStatus::Submitted, $report->status);
        $this->assertSame($this->kebeleA1->id, $report->location_id);

        $this->actingAs($this->zoneUser)->post(route('reports.approve', $report))->assertForbidden();

        $this->actingAs($this->woredaUserA)->post(route('reports.verify', $report), ['note' => 'ተመልክቻለሁ'])->assertRedirect();
        $report->refresh();
        $this->assertSame(ReportStatus::WoredaVerified, $report->status);
        $this->assertSame($this->woredaUserA->id, $report->woreda_verified_by);

        $this->actingAs($this->woredaUserA)->post(route('reports.verify', $report))->assertForbidden();

        $this->actingAs($this->zoneUser)->post(route('reports.approve', $report))->assertRedirect();
        $report->refresh();
        $this->assertSame(ReportStatus::ZoneApproved, $report->status);
        $this->assertSame(['create', 'submit', 'verify', 'approve'], $report->actions->pluck('action')->all());
    }

    public function test_saving_a_draft_does_not_submit(): void
    {
        $this->actingAs($this->expertA)->post('/reports', $this->reportPayload(['action' => 'save']))->assertRedirect();
        $this->assertSame(ReportStatus::Draft, ExtensionReport::firstOrFail()->status);
    }

    public function test_woreda_cannot_act_on_other_woreda_and_kebele_cannot_see_others(): void
    {
        $this->actingAs($this->expertA)->post('/reports', $this->reportPayload(['action' => 'submit']));
        $report = ExtensionReport::firstOrFail();

        $this->actingAs($this->woredaUserB)->get(route('reports.show', $report))->assertForbidden();
        $this->actingAs($this->woredaUserB)->post(route('reports.verify', $report))->assertForbidden();
        $this->actingAs($this->expertB)->get(route('reports.show', $report))->assertForbidden();
        $this->actingAs($this->woredaUserA)->get(route('reports.show', $report))->assertOk();
    }

    public function test_return_requires_reason_and_allows_resubmission(): void
    {
        $this->actingAs($this->expertA)->post('/reports', $this->reportPayload(['action' => 'submit']));
        $report = ExtensionReport::firstOrFail();

        $this->actingAs($this->woredaUserA)->post(route('reports.return', $report), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->actingAs($this->woredaUserA)->post(route('reports.return', $report), ['reason' => 'ቁጥሮቹ አይመጣጠኑም'])->assertRedirect();
        $report->refresh();
        $this->assertSame(ReportStatus::Returned, $report->status);
        $this->assertSame('ቁጥሮቹ አይመጣጠኑም', $report->return_reason);

        $this->actingAs($this->expertA)->put(route('reports.update', $report), $this->reportPayload(['farmers_total' => 9, 'new_farmers' => 4, 'action' => 'submit']))->assertRedirect();
        $report->refresh();
        $this->assertSame(ReportStatus::Submitted, $report->status);
        $this->assertNull($report->return_reason);
        $this->assertSame(9, $report->farmers_total);
    }

    public function test_submitted_report_is_locked_for_the_expert(): void
    {
        $this->actingAs($this->expertA)->post('/reports', $this->reportPayload(['action' => 'submit']));
        $report = ExtensionReport::firstOrFail();

        $this->actingAs($this->expertA)->put(route('reports.update', $report), $this->reportPayload())->assertForbidden();
        $this->actingAs($this->expertA)->delete(route('reports.destroy', $report))->assertForbidden();
    }

    public function test_validation_rules(): void
    {
        $this->actingAs($this->expertA);

        $this->post('/reports', $this->reportPayload(['new_farmers' => 1]))->assertSessionHasErrors('new_farmers');
        $this->post('/reports', $this->reportPayload(['female_farmers' => 20]))->assertSessionHasErrors('female_farmers');
        $this->post('/reports', $this->reportPayload(['report_date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('report_date');
        $this->post('/reports', $this->reportPayload(['service_types' => ['bogus']]))->assertSessionHasErrors('service_types.0');

        $this->post('/reports', $this->reportPayload())->assertSessionHasNoErrors();
        $this->post('/reports', $this->reportPayload())->assertSessionHasErrors('report_date');
    }

    public function test_only_kebele_experts_create_reports(): void
    {
        $this->actingAs($this->woredaUserA)->post('/reports', $this->reportPayload())->assertForbidden();
        $this->actingAs($this->zoneUser)->get('/reports/create')->assertForbidden();
    }
}
