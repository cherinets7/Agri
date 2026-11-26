<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\ExpertWeeklySummary;
use App\Models\ExtensionReport;
use App\Models\FarmerFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class FeedbackComparisonAnnualTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHierarchy();
    }

    public function test_farmer_feedback_via_qr_and_mobile_signature_with_scoping(): void
    {
        $token = $this->expertA->ensureFeedbackToken();

        // Public QR card & submission work without login
        $this->get(route('feedback.public', $token))->assertOk()->assertSee($this->expertA->name);

        $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $this->post(route('feedback.public.store', $token), [
            'rating' => 5,
            'service_type' => 'crop',
            'farmer_name' => 'አቶ ደመቀ',
            'comment' => 'በጣም ጥሩ አገልግሎት',
            'signature_data' => $sig,
        ])->assertRedirect(route('feedback.public', $token));

        $fb = FarmerFeedback::firstOrFail();
        $this->assertSame(5, $fb->rating);
        $this->assertSame($this->kebeleA1->id, $fb->location_id);
        $this->assertSame($sig, $fb->signature_data);

        // Expert A QR card SVG renders; Expert B cannot view Expert A's QR card
        $this->actingAs($this->expertA)->get(route('feedback.qr-card'))->assertOk()->assertSee('<svg', false);
        $this->actingAs($this->expertB)->get(route('feedback.qr-card', $this->expertA))->assertForbidden();

        // Woreda B cannot see Expert A's feedback
        $this->actingAs($this->woredaUserB)->get(route('feedback.index'))->assertOk()->assertDontSee('አቶ ደመቀ');
        $this->actingAs($this->woredaUserA)->get(route('feedback.index'))->assertOk()->assertSee('አቶ ደመቀ');
    }

    public function test_weekly_expert_summary_calculates_metrics_and_follows_approval_chain(): void
    {
        $monday = now()->startOfWeek();

        ExtensionReport::create([
            'user_id' => $this->expertA->id,
            'location_id' => $this->kebeleA1->id,
            'report_date' => $monday->toDateString(),
            'status' => ReportStatus::Submitted,
            'farmers_total' => 9,
            'new_farmers' => 4,
            'repeat_farmers' => 5,
            'female_farmers' => 3,
            'youth_farmers' => 4,
            'service_types' => ['crop'],
            'techniques' => ['home_visit'],
            'duration_minutes' => 180,
        ]);

        FarmerFeedback::create([
            'expert_id' => $this->expertA->id,
            'location_id' => $this->kebeleA1->id,
            'feedback_date' => $monday->toDateString(),
            'rating' => 4,
        ]);

        $this->actingAs($this->expertA)->post(route('summaries.store'), [
            'week_start' => $monday->toDateString(),
            'highlights' => 'የመስመር ዘር ድጋፍ ተደርጓል',
            'challenges' => 'ምንም',
            'next_week_plan' => 'ክትትል መቀጠል',
            'action' => 'submit',
        ])->assertRedirect();

        $summary = ExpertWeeklySummary::firstOrFail();
        $this->assertSame(1, $summary->days_reported);
        $this->assertSame(9, $summary->farmers_total);
        $this->assertEquals(4.0, $summary->feedback_avg);
        $this->assertSame(ReportStatus::Submitted, $summary->status);

        $this->actingAs($this->woredaUserB)->post(route('summaries.verify', $summary))->assertForbidden();
        $this->actingAs($this->woredaUserA)->post(route('summaries.verify', $summary))->assertRedirect();
        $this->actingAs($this->zoneUser)->post(route('summaries.approve', $summary))->assertRedirect();
        $this->assertSame(ReportStatus::ZoneApproved, $summary->fresh()->status);
    }

    public function test_comparison_dashboards_and_annual_excel_and_pdf_exports(): void
    {
        ExtensionReport::create([
            'user_id' => $this->expertA->id,
            'location_id' => $this->kebeleA1->id,
            'report_date' => today()->toDateString(),
            'status' => ReportStatus::ZoneApproved,
            'farmers_total' => 10,
            'new_farmers' => 6,
            'repeat_farmers' => 4,
            'female_farmers' => 4,
            'youth_farmers' => 5,
            'service_types' => ['crop'],
            'techniques' => ['home_visit'],
            'duration_minutes' => 200,
        ]);

        foreach (['month', 'quarter', 'year'] as $mode) {
            $this->actingAs($this->zoneUser)->get(route('comparisons.index', ['mode' => $mode]))
                ->assertOk()
                ->assertSee('የወር / ሩብ ዓመት / ዓመት ንጽጽር');
        }

        $this->actingAs($this->zoneUser)->get(route('annual.index'))
            ->assertOk()
            ->assertSee('ዓመታዊ የግብርና ክትትል ሪፖርት');

        $excel = $this->actingAs($this->zoneUser)->get(route('exports.annual.excel'));
        $excel->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $excel->headers->get('content-type'),
        );

        $pdf = $this->actingAs($this->zoneUser)->get(route('exports.annual.pdf'));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }
}
