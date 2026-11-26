<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Enums\PeriodGranularity;
use App\Enums\PeriodicReportType;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\ExtensionReport;
use App\Models\Location;
use App\Models\PeriodicReport;
use App\Models\User;
use App\Notifications\MissingReportNotification;
use App\Services\ExtensionPerformance;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PlanMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsSignaturePwaGisTest extends TestCase
{
    use RefreshDatabase;

    private Location $zone;

    private Location $fofa;

    private Location $toba;

    private Location $kebeleFofa;

    private Location $kebeleToba;

    private User $zoneUser;

    private User $woredaFofa;

    private User $expertFofa;

    private User $expertToba;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocationSeeder::class);
        $this->seed(PlanMatrixSeeder::class);

        $this->zone = Location::ofLevel(Level::Zone)->firstOrFail();
        $this->fofa = Location::where('code', 'YEM-FOFA')->firstOrFail();
        $this->toba = Location::where('code', 'YEM-TOBA')->firstOrFail();
        $this->kebeleFofa = Location::where('parent_id', $this->fofa->id)->firstOrFail();
        $this->kebeleToba = Location::where('parent_id', $this->toba->id)->firstOrFail();

        $this->zoneUser = User::factory()->create(['role' => Role::Zone, 'location_id' => $this->zone->id]);
        $this->woredaFofa = User::factory()->create(['role' => Role::Woreda, 'location_id' => $this->fofa->id]);
        $this->expertFofa = User::factory()->create(['role' => Role::Kebele, 'location_id' => $this->kebeleFofa->id]);
        $this->expertToba = User::factory()->create(['role' => Role::Kebele, 'location_id' => $this->kebeleToba->id]);
    }

    public function test_missing_report_reminders_create_in_app_and_email_notifications_without_duplicates(): void
    {
        $lastWork = ExtensionPerformance::lastWorkDay();

        // expertFofa submitted a report; expertToba did not.
        ExtensionReport::create([
            'user_id' => $this->expertFofa->id,
            'location_id' => $this->kebeleFofa->id,
            'report_date' => $lastWork->toDateString(),
            'status' => ReportStatus::Submitted,
            'farmers_total' => 8,
            'new_farmers' => 4,
            'repeat_farmers' => 4,
            'female_farmers' => 3,
            'youth_farmers' => 2,
            'service_types' => ['crop'],
            'techniques' => ['household_visit'],
            'duration_minutes' => 90,
            'declared_at' => now(),
            'submitted_at' => now(),
        ]);

        // Verify notification channels (database + mail) via Notification::fake in isolated check
        $this->assertSame(['database', 'mail'], (new MissingReportNotification(
            'missing_daily',
            $lastWork,
            'ቀበሌ',
            route('reports.create'),
            true
        ))->via($this->expertToba));

        $this->assertSame(['database'], (new MissingReportNotification(
            'missing_daily',
            $lastWork,
            'ቀበሌ',
            route('reports.create'),
            false
        ))->via($this->expertToba));

        // Run real database persistence via HTTP action & verify deduplication
        $this->actingAs($this->zoneUser)
            ->post(route('notifications.remind-missing'), ['send_email' => '1'])
            ->assertRedirect();

        $this->assertSame(1, $this->expertToba->unreadNotifications()->count());
        $this->assertSame(0, $this->expertFofa->unreadNotifications()->count());

        // Second run on same day should not duplicate daily reminder
        $this->artisan('agri:remind-missing', ['--date' => $lastWork->toDateString(), '--no-weekly' => true, '--no-email' => true])
            ->assertSuccessful();
        $this->assertSame(1, $this->expertToba->fresh()->unreadNotifications()->count());

        // Expert reads notification via UI
        $notifId = $this->expertToba->unreadNotifications()->first()->id;
        $this->actingAs($this->expertToba)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('ያልተላከ ዕለታዊ ሪፖርት');

        $this->actingAs($this->expertToba)->post(route('notifications.read', $notifId))
            ->assertRedirect(route('reports.create'));
        $this->assertSame(0, $this->expertToba->fresh()->unreadNotifications()->count());

        // Kebele expert cannot trigger reminder dispatch
        $this->actingAs($this->expertToba)
            ->post(route('notifications.remind-missing'))
            ->assertForbidden();
    }

    public function test_digital_signatures_persist_across_submit_verify_approve_and_notify_expert(): void
    {
        $sig1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $sig2 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEklEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $sig3 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAYAAABWKLW/AAAAEklEQVR42mNgYGBg+M/AAIwAAEYAAwE0xK8AAAAASUVORK5CYII=';

        $this->actingAs($this->expertFofa)->post(route('reports.store'), [
            'report_date' => today()->toDateString(),
            'farmers_total' => 9,
            'new_farmers' => 4,
            'repeat_farmers' => 5,
            'female_farmers' => 4,
            'youth_farmers' => 3,
            'service_types' => ['crop'],
            'techniques' => ['household_visit'],
            'duration_minutes' => 120,
            'signature_data' => $sig1,
            'action' => 'submit',
        ])->assertRedirect();

        $report = ExtensionReport::latest('id')->firstOrFail();
        $this->assertSame($sig1, $report->signature_data);

        $this->actingAs($this->woredaFofa)->post(route('reports.verify', $report), [
            'note' => 'Verified',
            'signature_data' => $sig2,
        ])->assertRedirect();

        $this->actingAs($this->zoneUser)->post(route('reports.approve', $report), [
            'note' => 'Approved',
            'signature_data' => $sig3,
        ])->assertRedirect();

        $report->refresh();
        $this->assertSame($sig2, $report->verified_signature_data);
        $this->assertSame($sig3, $report->approved_signature_data);
        $this->assertSame(1, $this->expertFofa->unreadNotifications()->count());

        $this->actingAs($this->expertFofa)->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee($sig1)
            ->assertSee($sig2)
            ->assertSee($sig3);

        // Also check PeriodicReport signature capture & Ethiopian date picker presence
        $this->actingAs($this->expertFofa)->get(route('periodic.create', PeriodicReportType::CropArea->value))
            ->assertOk()
            ->assertSee('data-et-date-picker', false)
            ->assertSee('data-sig-wrap', false);

        $this->actingAs($this->expertFofa)->post(route('periodic.store', PeriodicReportType::CropArea->value), [
            'report_date' => today()->toDateString(),
            'period_type' => PeriodGranularity::Weekly->value,
            'payload' => ['season' => 'meher', 'maize_ha' => 5, 'wheat_ha' => 3, 'barley_ha' => 2, 'other_ha' => 1],
            'signature_data' => $sig1,
            'action' => 'submit',
        ])->assertRedirect();

        $periodic = PeriodicReport::latest('id')->firstOrFail();
        $this->assertSame($sig1, $periodic->signature_data);
    }

    public function test_pwa_assets_and_gis_choropleth_map_work_with_role_scoping(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('js/agri-ui.js'));
        $this->assertFileExists(public_path('icons/icon.svg'));
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $this->assertFileExists(public_path('geo/yem-woredas.geojson'));

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('/dashboard', $manifest['start_url']);

        // Layout includes manifest link & notification bell
        $this->actingAs($this->zoneUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('agri-ui.js', false);

        // Map access: Zone sees all 4 woredas, Woreda sees only own, Kebele gets 403
        $this->actingAs($this->zoneUser)->get(route('map.index'))
            ->assertOk()
            ->assertSee('ፎፋ ወረዳ')
            ->assertSee('ቶባ ወረዳ');

        $this->actingAs($this->woredaFofa)->get(route('map.index'))
            ->assertOk()
            ->assertSee('ፎፋ ወረዳ')
            ->assertDontSee('ቶባ ወረዳ');

        $this->actingAs($this->expertFofa)->get(route('map.index'))
            ->assertForbidden();
    }
}
