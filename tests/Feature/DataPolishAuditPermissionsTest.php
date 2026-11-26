<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Contracts\SmsGateway;
use App\Enums\Level;
use App\Enums\PlanUnit;
use App\Enums\Role;
use App\Enums\WeeklyPlanStatus;
use App\Models\AuditLog;
use App\Models\ExtensionReport;
use App\Models\KebeleWeeklyActual;
use App\Models\Location;
use App\Models\PlanActivity;
use App\Models\PlanTarget;
use App\Models\RolePermission;
use App\Models\StrategicGoal;
use App\Models\User;
use App\Models\WeeklyActual;
use App\Models\WeeklyPlanReport;
use App\Notifications\MissingReportNotification;
use App\Services\MatrixQuality;
use App\Services\PlanMatrix;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\NullSmsGateway;
use App\Support\EthiopianDate;
use App\Support\Permissions;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PlanMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class DataPolishAuditPermissionsTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    private function useHierarchy(): void
    {
        $this->buildHierarchy();
    }

    public function test_seeded_locations_are_clearer_and_flagged_as_editable(): void
    {
        $this->seed(LocationSeeder::class);

        $this->assertDatabaseHas('locations', ['code' => 'YEM-FOFA', 'name_am' => 'ፎፋ ወረዳ', 'name_confirmed' => true]);

        $skar = Location::where('code', 'YEM-SKAR')->firstOrFail();
        $this->assertStringContainsString('ሳ/ከ/አስ/ር', $skar->name_am, 'original abbreviation is kept');
        $this->assertNotSame('ሳ/ከ/አስ/ር', $skar->name_am);
        $this->assertFalse($skar->name_confirmed);

        $kebeles = Location::ofLevel(Level::Kebele)->orderBy('id')->get();
        $this->assertCount(12, $kebeles);
        $this->assertSame('ቀበሌ 01', $kebeles->first()->name_am);
        $this->assertSame('ቀበሌ 12', $kebeles->last()->name_am);
        $this->assertTrue($kebeles->every->is_placeholder);
        $this->assertSame('YEM-SKAR-K1', $kebeles->first()->code, 'codes unchanged');
    }

    public function test_zone_renaming_confirms_a_name_and_reseeding_never_overwrites_it(): void
    {
        $this->seed(LocationSeeder::class);
        $zoneUser = User::factory()->create(['role' => Role::Zone, 'location_id' => Location::ofLevel(Level::Zone)->value('id')]);
        $kebele = Location::where('code', 'YEM-FOFA-K1')->firstOrFail();
        $woreda = Location::where('code', 'YEM-DSZ')->firstOrFail();

        $this->actingAs($zoneUser)->get('/locations')->assertOk()
            ->assertSee('ናሙና — ሊስተካከል የሚችል')->assertSee('ግምት — ያረጋግጡ');

        $this->actingAs($zoneUser)->put("/locations/{$kebele->id}", ['name_am' => 'ጨንጮ ቀበሌ'])->assertRedirect('/locations');
        $this->actingAs($zoneUser)->put("/locations/{$woreda->id}", ['name_am' => 'ደቡብ ሳጃ ዙሪያ ወረዳ'])->assertRedirect('/locations');

        $kebele->refresh();
        $this->assertSame('ጨንጮ ቀበሌ', $kebele->name_am);
        $this->assertFalse($kebele->is_placeholder);
        $this->assertTrue($woreda->refresh()->name_confirmed);

        $this->seed(LocationSeeder::class);
        $this->assertSame('ጨንጮ ቀበሌ', $kebele->refresh()->name_am);
        $this->assertSame('ደቡብ ሳጃ ዙሪያ ወረዳ', $woreda->refresh()->name_am);
        $this->assertSame('ቀበሌ 08', Location::where('code', 'YEM-FOFA-K2')->value('name_am'));
    }

    public function test_woreda_can_rename_only_own_kebeles_and_kebele_cannot(): void
    {
        $this->useHierarchy();

        $this->actingAs($this->woredaUserA)->put("/locations/{$this->kebeleA1->id}", ['name_am' => 'አዲስ ስም'])->assertRedirect();
        $this->actingAs($this->woredaUserA)->put("/locations/{$this->kebeleB1->id}", ['name_am' => 'x'])->assertForbidden();
        $this->actingAs($this->woredaUserA)->put("/locations/{$this->woredaA->id}", ['name_am' => 'x'])->assertForbidden();
        $this->actingAs($this->expertA)->put("/locations/{$this->kebeleA1->id}", ['name_am' => 'x'])->assertForbidden();
    }

    public function test_matrix_typo_is_fixed_and_admin_notes_flag_unit_issues_for_zone_only(): void
    {
        $this->seed(LocationSeeder::class);
        $this->seed(PlanMatrixSeeder::class);

        $hive = PlanActivity::where('name_am', 'ባህላዊ ቀፎ')->firstOrFail();
        $zone = Location::ofLevel(Level::Zone)->firstOrFail();
        $this->assertEquals(57800, PlanTarget::where(['plan_activity_id' => $hive->id, 'location_id' => $zone->id])->value('target'));
        $this->assertStringNotContainsString('578001806', json_encode(PlanMatrixSeeder::data()['goals']));

        // A target seeded earlier with the typo is repaired; an edited value is left alone.
        PlanTarget::where(['plan_activity_id' => $hive->id, 'location_id' => $zone->id])->update(['target' => 578001806]);
        $this->seed(PlanMatrixSeeder::class);
        $this->assertEquals(57800, PlanTarget::where(['plan_activity_id' => $hive->id, 'location_id' => $zone->id])->value('target'));
        PlanTarget::where(['plan_activity_id' => $hive->id, 'location_id' => $zone->id])->update(['target' => 60000]);
        $this->seed(PlanMatrixSeeder::class);
        $this->assertEquals(60000, PlanTarget::where(['plan_activity_id' => $hive->id, 'location_id' => $zone->id])->value('target'));

        $zoneUser = User::factory()->create(['role' => Role::Zone, 'location_id' => $zone->id]);
        $woredaUser = User::factory()->create(['role' => Role::Woreda, 'location_id' => Location::where('code', 'YEM-FOFA')->value('id')]);

        $this->actingAs($zoneUser)->get('/matrix?year=2018')->assertOk()
            ->assertSee('የአስተዳደር ማስታወሻ')->assertSee('ባህላዊ ቀፎ')->assertSee('ኮረሪማ ልማት');
        $this->actingAs($woredaUser)->get('/matrix?year=2018')->assertOk()->assertDontSee('የአስተዳደር ማስታወሻ');
    }

    public function test_matrix_quality_flags_extreme_plan_actual_ratios(): void
    {
        $this->useHierarchy();
        $goal = StrategicGoal::create(['number' => 1, 'name_am' => 'ግብ', 'sort_order' => 1]);
        $a = PlanActivity::create(['strategic_goal_id' => $goal->id, 'name_am' => 'ተግባር ሀ', 'unit' => PlanUnit::Count, 'sort_order' => 1]);
        $year = EthiopianDate::fiscalYear(today());
        PlanTarget::create(['plan_activity_id' => $a->id, 'location_id' => $this->zone->id, 'fiscal_year' => $year, 'target' => 1000000]);
        $week = PlanMatrix::weekStart(today());
        $report = WeeklyPlanReport::create([
            'woreda_id' => $this->woredaA->id, 'user_id' => $this->woredaUserA->id, 'fiscal_year' => $year,
            'week_start' => $week, 'status' => WeeklyPlanStatus::Submitted,
        ]);
        $report->actuals()->create(['plan_activity_id' => $a->id, 'value' => 50]);

        $flags = app(MatrixQuality::class)->flags(app(PlanMatrix::class)->build($this->zoneUser, $year));
        $this->assertSame(['under'], array_column($flags, 'kind'));
        $this->assertSame('ተግባር ሀ', $flags[0]['activity']);
    }

    public function test_geojson_has_real_outline_with_four_woreda_codes_and_clear_todo(): void
    {
        $geo = json_decode(file_get_contents(public_path('geo/yem-woredas.geojson')), true);

        $this->assertSame('FeatureCollection', $geo['type']);
        $this->assertStringContainsString('geoBoundaries', $geo['_source']);
        $this->assertStringContainsString('SCHEMATIC SPLIT', $geo['_todo']);
        $this->assertEqualsCanonicalizing(['YEM-SKAR', 'YEM-DSZ', 'YEM-FOFA', 'YEM-TOBA'], array_column(array_column($geo['features'], 'properties'), 'code'));

        foreach ($geo['features'] as $feature) {
            $ring = $feature['geometry']['coordinates'][0];
            $this->assertGreaterThanOrEqual(8, count($ring));
            $this->assertSame($ring[0], end($ring), 'ring is closed');
            foreach ($ring as [$lon, $lat]) {
                $this->assertGreaterThan(37.3, $lon);
                $this->assertLessThan(37.7, $lon);
                $this->assertGreaterThan(7.5, $lat);
                $this->assertLessThan(8.1, $lat);
            }
        }

        $this->seed(LocationSeeder::class);
        $zoneUser = User::factory()->create(['role' => Role::Zone, 'location_id' => Location::ofLevel(Level::Zone)->value('id')]);
        $this->actingAs($zoneUser)->get('/map')->assertOk()->assertSee('ናሙና (schematic)', false)->assertSee('geoBoundaries');
    }

    public function test_kebele_weekly_entry_is_off_by_default(): void
    {
        $this->useHierarchy();
        $this->assertFalse(config('plan.kebele_weekly_entry'));

        $this->actingAs($this->expertA)->get('/kebele-weekly')->assertForbidden();
        $this->actingAs($this->expertA)->get('/dashboard')->assertDontSee('የቀበሌ ሳምንታዊ ክንውን');
    }

    public function test_kebele_weekly_entry_aggregates_into_woreda_form_when_enabled(): void
    {
        config(['plan.kebele_weekly_entry' => true]);
        $this->useHierarchy();
        $kebeleA2 = Location::create(['level' => Level::Kebele, 'name_am' => 'ቀበሌ ሐ', 'parent_id' => $this->woredaA->id]);
        $expertA2 = $this->makeUser(Role::Kebele, $kebeleA2, 'ka2@t.test');

        $goal = StrategicGoal::create(['number' => 1, 'name_am' => 'ግብ', 'sort_order' => 1]);
        $a = PlanActivity::create(['strategic_goal_id' => $goal->id, 'name_am' => 'ተፋሰስ', 'unit' => PlanUnit::Hectare, 'sort_order' => 1]);
        $b = PlanActivity::create(['strategic_goal_id' => $goal->id, 'name_am' => 'ወተት', 'unit' => PlanUnit::Liter, 'sort_order' => 2]);
        $week = PlanMatrix::weekStart(today());

        $this->actingAs($this->expertA)->get('/kebele-weekly')->assertOk()->assertSee('የቀበሌ ሳምንታዊ ክንውን');
        $this->actingAs($this->expertA)->put('/kebele-weekly', ['week_start' => $week->toDateString(), 'values' => [$a->id => '10', $b->id => '']])->assertRedirect();
        $this->actingAs($expertA2)->put('/kebele-weekly', ['week_start' => $week->toDateString(), 'values' => [$a->id => '2.5', $b->id => '0']])->assertRedirect();
        $this->actingAs($this->expertB)->put('/kebele-weekly', ['week_start' => $week->toDateString(), 'values' => [$a->id => '99']])->assertRedirect();

        $this->assertSame(4, KebeleWeeklyActual::count());
        $this->actingAs($this->expertA)->put('/kebele-weekly', ['week_start' => $week->toDateString(), 'values' => [$a->id => '12']]);
        $this->assertEquals(12, KebeleWeeklyActual::where(['kebele_id' => $this->kebeleA1->id, 'plan_activity_id' => $a->id])->value('value'));

        $response = $this->actingAs($this->woredaUserA)->get('/weekly-plan/create?week='.$week->toDateString())->assertOk();
        $response->assertSee('2 ከ 2 ቀበሌዎች')->assertSee('የቀበሌዎች ድምር');
        $response->assertSee('value="14.5"', false);

        $this->actingAs($this->woredaUserA)->post('/weekly-plan', [
            'week_start' => $week->toDateString(), 'values' => [$a->id => '14.5'], 'action' => 'submit',
        ])->assertRedirect();
        $this->assertSame(0, WeeklyActual::where('plan_activity_id', $b->id)->count());

        // The matrix counts only the woreda report: kebele rows are never added on top.
        $row = collect(app(PlanMatrix::class)->build($this->zoneUser, EthiopianDate::fiscalYear($week))['goals'])->flatMap(fn ($g) => $g['rows'])->firstWhere('activity.id', $a->id);
        $this->assertEquals(14.5, $row['zone_actual']);

        $this->actingAs($this->woredaUserA)->get('/kebele-weekly')->assertForbidden();
        $this->actingAs($this->expertA)->put('/kebele-weekly', ['week_start' => $week->copy()->addWeeks(3)->toDateString(), 'values' => [$a->id => '1']])->assertStatus(422);
    }

    public function test_kebele_weekly_entry_respects_role_permission_toggle(): void
    {
        config(['plan.kebele_weekly_entry' => true]);
        $this->useHierarchy();

        $this->actingAs($this->expertA)->get('/kebele-weekly')->assertOk();
        Permissions::set(Role::Kebele, 'kebele_weekly.enter', false, $this->zoneUser);
        $this->actingAs($this->expertA)->get('/kebele-weekly')->assertForbidden();
    }

    public function test_audit_log_records_create_update_delete_and_logins(): void
    {
        $this->useHierarchy();
        $this->actingAs($this->expertA)->post('/reports', $this->reportPayload(['action' => 'save']))->assertRedirect();
        $report = ExtensionReport::firstOrFail();

        $created = AuditLog::where(['event' => 'created', 'auditable_type' => ExtensionReport::class, 'auditable_id' => $report->id])->firstOrFail();
        $this->assertSame($this->expertA->id, $created->user_id);

        $this->actingAs($this->expertA)->put(route('reports.update', $report), $this->reportPayload(['farmers_total' => 9, 'new_farmers' => 4, 'action' => 'save']))->assertRedirect();
        $updated = AuditLog::where(['event' => 'updated', 'auditable_id' => $report->id])->latest('id')->firstOrFail();
        $this->assertSame(9, (int) $updated->changes['farmers_total']['new']);
        $this->assertSame(8, (int) $updated->changes['farmers_total']['old']);

        $this->actingAs($this->expertA)->delete(route('reports.destroy', $report))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => ExtensionReport::class, 'auditable_id' => $report->id]);

        $this->actingAs($this->zoneUser)->post('/users', [
            'name' => 'አዲስ ባለሙያ', 'email' => 'new@t.test', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
            'role' => 'kebele', 'location_id' => $this->kebeleA1->id, 'is_active' => 1,
        ]);
        $userLog = AuditLog::where(['event' => 'created', 'auditable_type' => User::class])->latest('id')->first();
        $this->assertNotNull($userLog);
        $this->assertArrayNotHasKey('password', $userLog->changes);
        $this->assertStringNotContainsString('Secret-pass', json_encode(AuditLog::all()));

        $this->post('/logout');
        $this->post('/login', ['email' => 'wa@t.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed']);
        $this->post('/login', ['email' => 'wa@t.test', 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $this->woredaUserA->id]);
    }

    public function test_audit_covers_plan_locations_and_matrix_edits_and_is_zone_only(): void
    {
        $this->useHierarchy();
        $goal = StrategicGoal::create(['number' => 1, 'name_am' => 'ግብ', 'sort_order' => 1]);
        $before = AuditLog::count();
        $this->assertSame(0, $before, 'writes without a signed-in user (seeders, console) are not logged');

        $this->actingAs($this->zoneUser)->post('/plan/activities', ['strategic_goal_id' => $goal->id, 'name_am' => 'ተግባር', 'unit' => 'ton'])->assertRedirect();
        $this->actingAs($this->zoneUser)->post('/locations', ['parent_id' => $this->woredaA->id, 'name_am' => 'አዲስ'])->assertRedirect();

        $types = AuditLog::pluck('auditable_type')->all();
        $this->assertContains(PlanActivity::class, $types);
        $this->assertContains(Location::class, $types);

        $this->actingAs($this->zoneUser)->get('/audit')->assertOk()->assertSee('ተፈጠረ')->assertSee('አካባቢ');
        $this->actingAs($this->zoneUser)->get('/audit?event=deleted')->assertOk()->assertSee('ምንም መዝገብ የለም');
        $this->actingAs($this->woredaUserA)->get('/audit')->assertForbidden();
        $this->actingAs($this->expertA)->get('/audit')->assertForbidden();
    }

    public function test_audit_can_be_switched_off(): void
    {
        config(['audit.enabled' => false]);
        $this->useHierarchy();
        $this->actingAs($this->expertA)->post('/reports', $this->reportPayload(['action' => 'save']))->assertRedirect();
        $this->assertSame(0, AuditLog::count());
    }

    public function test_zone_toggles_role_capabilities_and_changes_are_audited(): void
    {
        $this->useHierarchy();

        $this->actingAs($this->zoneUser)->get('/admin/permissions')->assertOk()->assertSee('ሳምንታዊ ክንውን (ወረዳ) ማስገባትና መላክ');
        $this->actingAs($this->woredaUserA)->get('/admin/permissions')->assertForbidden();
        $this->actingAs($this->expertA)->put('/admin/permissions', [])->assertForbidden();

        $this->actingAs($this->woredaUserA)->get('/weekly-plan/create')->assertOk();
        $this->actingAs($this->woredaUserA)->get('/users')->assertOk();

        $defaults = [];
        foreach (Role::cases() as $role) {
            foreach (array_keys(Permissions::definitions()) as $cap) {
                if (Permissions::roleAllows($role, $cap)) {
                    $defaults[$role->value][$cap] = '1';
                }
            }
        }
        unset($defaults['woreda']['weekly.enter'], $defaults['woreda']['users.manage']);

        $this->actingAs($this->zoneUser)->put('/admin/permissions', ['perm' => $defaults])->assertRedirect('/admin/permissions');

        $this->assertSame(2, RolePermission::count());
        $this->actingAs($this->woredaUserA)->get('/weekly-plan/create')->assertForbidden();
        $this->actingAs($this->woredaUserA)->get('/users')->assertForbidden();
        $this->actingAs($this->woredaUserA)->get('/matrix')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'permissions_changed']);

        // Re-enabling returns to defaults and removes the override rows.
        $defaults['woreda']['weekly.enter'] = '1';
        $defaults['woreda']['users.manage'] = '1';
        $this->actingAs($this->zoneUser)->put('/admin/permissions', ['perm' => $defaults])->assertRedirect();
        $this->assertSame(0, RolePermission::count());
        $this->actingAs($this->woredaUserA)->get('/weekly-plan/create')->assertOk();
    }

    public function test_locked_capability_cannot_be_removed_from_zone_and_zone_can_disable_matrix_for_woreda(): void
    {
        $this->useHierarchy();

        $this->actingAs($this->zoneUser)->put('/admin/permissions', ['perm' => []])->assertRedirect();
        $this->actingAs($this->zoneUser)->get('/admin/permissions')->assertOk();
        $this->assertTrue(Permissions::roleAllows(Role::Zone, 'permissions.manage'));
        $this->assertFalse(Permissions::roleAllows(Role::Zone, 'plan.manage'));
        $this->assertFalse(Permissions::roleAllows(Role::Woreda, 'matrix.view'));

        $this->actingAs($this->woredaUserA)->get('/matrix')->assertForbidden();
        $this->actingAs($this->zoneUser)->get('/plan')->assertForbidden();
        $this->actingAs($this->zoneUser)->put('/admin/permissions', ['perm' => ['zone' => ['plan.manage' => '1', 'matrix.view' => '1'], 'woreda' => ['matrix.view' => '1']]])->assertRedirect();
        $this->actingAs($this->zoneUser)->get('/plan')->assertOk();
    }

    public function test_weekly_approve_permission_can_be_taken_from_zone(): void
    {
        $this->useHierarchy();
        $goal = StrategicGoal::create(['number' => 1, 'name_am' => 'ግብ', 'sort_order' => 1]);
        $a = PlanActivity::create(['strategic_goal_id' => $goal->id, 'name_am' => 'ተግባር', 'unit' => PlanUnit::Count, 'sort_order' => 1]);
        $week = PlanMatrix::weekStart(today());

        $this->actingAs($this->woredaUserA)->post('/weekly-plan', ['week_start' => $week->toDateString(), 'values' => [$a->id => 5], 'action' => 'submit'])->assertRedirect();
        $report = WeeklyPlanReport::firstOrFail();

        Permissions::set(Role::Zone, 'weekly.approve', false, $this->zoneUser);
        $this->actingAs($this->zoneUser)->post(route('weekly.approve', $report))->assertForbidden();
        Permissions::set(Role::Zone, 'weekly.approve', true, $this->zoneUser);
        $this->actingAs($this->zoneUser)->post(route('weekly.approve', $report))->assertRedirect();
    }

    public function test_sms_stub_gateway_logs_by_default_and_reminders_use_it_only_when_enabled(): void
    {
        $this->assertInstanceOf(LogSmsGateway::class, app(SmsGateway::class));
        config(['services.sms.driver' => 'null']);
        $this->assertInstanceOf(NullSmsGateway::class, app(SmsGateway::class));
        $this->assertFalse(app(SmsGateway::class)->send('0911000000', 'ሙከራ'));

        config(['services.sms.driver' => 'log']);
        Log::spy();
        $this->assertTrue(app(SmsGateway::class)->send('0911000000', 'ሙከራ'));
        Log::shouldHaveReceived('info')->withArgs(fn ($m) => str_contains($m, '[SMS stub] to 0911000000'))->once();

        $this->useHierarchy();
        $this->expertA->update(['phone' => '0911000001']);
        $notification = new MissingReportNotification('missing_daily', today(), 'ቀበሌ ሀ', 'http://x', false);
        $this->assertNotContains(SmsChannel::class, $notification->via($this->expertA));
        config(['extension.sms_reminders_enabled' => true]);
        $this->assertContains(SmsChannel::class, $notification->via($this->expertA));
        $this->assertStringContainsString('ቀበሌ ሀ', $notification->toSms($this->expertA));

        Notification::fake();
        $this->expertA->notify($notification);
        Notification::assertSentTo($this->expertA, MissingReportNotification::class);
    }
}
