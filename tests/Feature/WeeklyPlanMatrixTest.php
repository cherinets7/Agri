<?php

namespace Tests\Feature;

use App\Enums\PlanUnit;
use App\Enums\WeeklyPlanStatus;
use App\Exports\MatrixExport;
use App\Models\PlanActivity;
use App\Models\PlanTarget;
use App\Models\StrategicGoal;
use App\Models\User;
use App\Models\WeeklyPlanReport;
use App\Services\PlanMatrix;
use App\Services\WeeklyPlanWorkflow;
use App\Support\EthiopianDate;
use Database\Seeders\PlanMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsHierarchy;
use Tests\TestCase;

class WeeklyPlanMatrixTest extends TestCase
{
    use BuildsHierarchy;
    use RefreshDatabase;

    private PlanActivity $terraces;

    private PlanActivity $milk;

    private int $year;

    private Carbon $week1;

    private Carbon $week2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHierarchy();

        $goal = StrategicGoal::create(['number' => 1, 'name_am' => 'የአዝርዕት ሰብሎች', 'sort_order' => 1]);
        $this->terraces = PlanActivity::create(['strategic_goal_id' => $goal->id, 'name_am' => 'የተፋሰስ ልማት', 'unit' => PlanUnit::Hectare, 'sort_order' => 1]);
        $this->milk = PlanActivity::create(['strategic_goal_id' => $goal->id, 'name_am' => 'የወተት ምርት', 'unit' => PlanUnit::Liter, 'sort_order' => 2]);

        $this->week2 = PlanMatrix::weekStart(today()->subWeek());
        $this->week1 = $this->week2->copy()->subWeek();
        $this->year = EthiopianDate::fiscalYear($this->week2);
        $this->assertSame($this->year, EthiopianDate::fiscalYear($this->week1), 'test weeks must share a plan year');
    }

    private function plan(PlanActivity $activity, $location, float $target): void
    {
        PlanTarget::create(['plan_activity_id' => $activity->id, 'location_id' => $location->id, 'fiscal_year' => $this->year, 'target' => $target]);
    }

    private function weekly($woreda, User $user, Carbon $week, array $values, WeeklyPlanStatus $status = WeeklyPlanStatus::Submitted): WeeklyPlanReport
    {
        $report = WeeklyPlanReport::create([
            'woreda_id' => $woreda->id, 'user_id' => $user->id, 'fiscal_year' => EthiopianDate::fiscalYear($week),
            'week_start' => $week, 'status' => $status,
        ]);
        foreach ($values as $activityId => $value) {
            $report->actuals()->create(['plan_activity_id' => $activityId, 'value' => $value]);
        }

        return $report;
    }

    private function cell(array $matrix, PlanActivity $activity): array
    {
        foreach ($matrix['goals'] as $block) {
            foreach ($block['rows'] as $row) {
                if ($row['activity']->id === $activity->id) {
                    return $row;
                }
            }
        }
        $this->fail('activity row not found');
    }

    public function test_seeder_loads_the_word_matrix_structure(): void
    {
        PlanActivity::query()->delete();
        StrategicGoal::query()->delete();
        $this->seed(PlanMatrixSeeder::class);
        $this->seed(PlanMatrixSeeder::class);

        $this->assertSame(7, StrategicGoal::count());
        $this->assertSame(48, PlanActivity::count());
        $this->assertSame(PlanUnit::Hectare, PlanActivity::where('name_am', 'የፍራፍሬ ማሳ እንክብካቤ')->first()->unit);
        $this->assertSame(4242.0, (float) PlanTarget::where('plan_activity_id', PlanActivity::where('name_am', 'የፍራፍሬ ማሳ እንክብካቤ')->value('id'))->value('target'));
    }

    public function test_zone_totals_are_the_sum_of_woredas_and_only_counted_statuses_count(): void
    {
        $this->plan($this->terraces, $this->zone, 1000);
        $this->plan($this->terraces, $this->woredaA, 600);
        $this->plan($this->terraces, $this->woredaB, 400);

        $this->weekly($this->woredaA, $this->woredaUserA, $this->week1, [$this->terraces->id => 100, $this->milk->id => 5]);
        $this->weekly($this->woredaA, $this->woredaUserA, $this->week2, [$this->terraces->id => 50], WeeklyPlanStatus::Approved);
        $this->weekly($this->woredaB, $this->woredaUserB, $this->week1, [$this->terraces->id => 200]);
        $this->weekly($this->woredaB, $this->woredaUserB, $this->week2, [$this->terraces->id => 999], WeeklyPlanStatus::Draft);
        $this->weekly($this->woredaB, $this->woredaUserB, $this->week2->copy()->addWeek()->min(PlanMatrix::weekStart(today())), [$this->terraces->id => 777], WeeklyPlanStatus::Returned);

        $service = app(PlanMatrix::class);

        $cumulative = $service->build($this->zoneUser, $this->year, $this->week2);
        $row = $this->cell($cumulative, $this->terraces);
        $this->assertSame(1000.0, (float) $row['zone_plan']);
        $this->assertSame(350.0, $row['zone_actual']);
        $this->assertSame(150.0, $row['cells'][$this->woredaA->id]['actual']);
        $this->assertSame(200.0, $row['cells'][$this->woredaB->id]['actual']);
        $this->assertSame(25.0, $row['cells'][$this->woredaA->id]['pct']);
        $this->assertSame(35.0, $row['zone_pct']);
        $this->assertNull($this->cell($cumulative, $this->milk)['cells'][$this->woredaB->id]['actual']);

        $weekOnly = $this->cell($service->build($this->zoneUser, $this->year, $this->week2, weekOnly: true), $this->terraces);
        $this->assertSame(50.0, $weekOnly['zone_actual']);

        $approved = $this->cell($service->build($this->zoneUser, $this->year, $this->week2, approvedOnly: true), $this->terraces);
        $this->assertSame(50.0, $approved['zone_actual']);
    }

    public function test_zone_plan_falls_back_to_sum_of_woreda_plans(): void
    {
        $this->plan($this->terraces, $this->woredaA, 600);
        $this->plan($this->terraces, $this->woredaB, 400);
        $this->weekly($this->woredaA, $this->woredaUserA, $this->week1, [$this->terraces->id => 10]);

        $row = $this->cell(app(PlanMatrix::class)->build($this->zoneUser, $this->year), $this->terraces);
        $this->assertSame(1000.0, (float) $row['zone_plan']);
    }

    public function test_woreda_sees_only_own_column_and_no_zone_totals(): void
    {
        $this->plan($this->terraces, $this->woredaA, 600);
        $this->weekly($this->woredaA, $this->woredaUserA, $this->week1, [$this->terraces->id => 111]);
        $this->weekly($this->woredaB, $this->woredaUserB, $this->week1, [$this->terraces->id => 222]);

        $matrix = app(PlanMatrix::class)->build($this->woredaUserA, $this->year);
        $this->assertFalse($matrix['showZone']);
        $this->assertSame([$this->woredaA->id], $matrix['woredas']->pluck('id')->all());
        $row = $this->cell($matrix, $this->terraces);
        $this->assertNull($row['zone_actual']);
        $this->assertSame([$this->woredaA->id], array_keys($row['cells']));

        $page = $this->actingAs($this->woredaUserA)->get('/matrix?year='.$this->year);
        $page->assertOk()->assertSee('111')->assertDontSee('222')->assertDontSee('ዞናዊ ድምር');

        $this->actingAs($this->zoneUser)->get('/matrix?year='.$this->year)
            ->assertOk()->assertSee('ዞናዊ ድምር')->assertSee('333')
            ->assertSee('የተቋሙ ዋና ዋና ስትራተጂክ ግቦች')->assertSee('የተፋሰስ ልማት');
    }

    public function test_kebele_experts_cannot_access_matrix_or_weekly_reports(): void
    {
        $this->actingAs($this->expertA)->get('/matrix')->assertForbidden();
        $this->actingAs($this->expertA)->get('/weekly-plan')->assertForbidden();
        $this->actingAs($this->expertA)->get('/exports/matrix')->assertForbidden();
        $this->actingAs($this->expertA)->get('/plan')->assertForbidden();
        $this->actingAs($this->expertA)->get('/dashboard')->assertOk()->assertDontSee('ሳምንታዊ ማትሪክስ');
    }

    public function test_woreda_creates_submits_and_zone_approves(): void
    {
        $this->plan($this->terraces, $this->woredaA, 500);
        $week = $this->week1->toDateString();

        $this->actingAs($this->woredaUserA)->get('/weekly-plan/create?week='.$week)
            ->assertOk()->assertSee('የተፋሰስ ልማት')->assertSee('500');

        $response = $this->actingAs($this->woredaUserA)->post('/weekly-plan', [
            'week_start' => $this->week1->copy()->addDays(2)->toDateString(),
            'values' => [$this->terraces->id => '40.5', $this->milk->id => '0'],
            'action' => 'draft',
        ]);
        $report = WeeklyPlanReport::firstOrFail();
        $response->assertRedirect(route('weekly.show', $report));
        $this->assertSame($week, $report->week_start->toDateString(), 'normalised to Monday');
        $this->assertSame(WeeklyPlanStatus::Draft, $report->status);
        $this->assertSame(2, $report->actuals()->count(), 'zero is a real value');

        $this->actingAs($this->zoneUser)->post(route('weekly.approve', $report))->assertForbidden();
        $this->actingAs($this->woredaUserB)->get(route('weekly.show', $report))->assertForbidden();
        $this->actingAs($this->zoneUser)->get(route('weekly.show', $report))->assertOk();

        $this->actingAs($this->woredaUserA)->post(route('weekly.submit', $report))->assertRedirect();
        $this->assertSame(WeeklyPlanStatus::Submitted, $report->fresh()->status);
        $this->actingAs($this->woredaUserA)->get(route('weekly.edit', $report))->assertForbidden();
        $this->actingAs($this->woredaUserA)->post(route('weekly.approve', $report))->assertForbidden();

        $this->actingAs($this->zoneUser)->post(route('weekly.return', $report), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->zoneUser)->post(route('weekly.return', $report), ['reason' => 'ቁጥሮች ይታረሙ'])->assertRedirect();
        $report->refresh();
        $this->assertSame(WeeklyPlanStatus::Returned, $report->status);
        $this->assertSame('ቁጥሮች ይታረሙ', $report->return_reason);

        $this->actingAs($this->woredaUserA)->put(route('weekly.update', $report), [
            'values' => [$this->terraces->id => '45', $this->milk->id => ''],
            'action' => 'submit',
        ])->assertRedirect(route('weekly.show', $report));
        $report->refresh();
        $this->assertSame(WeeklyPlanStatus::Submitted, $report->status);
        $this->assertNull($report->return_reason);
        $this->assertSame(1, $report->actuals()->count(), 'blank removes the row');

        $this->actingAs($this->zoneUser)->post(route('weekly.approve', $report), ['note' => 'እሺ'])->assertRedirect();
        $this->assertSame(WeeklyPlanStatus::Approved, $report->fresh()->status);
        $this->assertSame(['create', 'submit', 'return', 'submit', 'approve'], $report->actions()->pluck('action')->all());
        $this->actingAs($this->zoneUser)->get(route('weekly.show', $report))->assertOk()->assertSee('በዞን ጸድቋል');
    }

    public function test_weekly_report_validation(): void
    {
        $user = $this->woredaUserA;

        $this->actingAs($user)->post('/weekly-plan', [
            'week_start' => today()->addWeeks(2)->toDateString(), 'values' => [$this->terraces->id => 1],
        ])->assertSessionHasErrors('week_start');

        $this->actingAs($user)->post('/weekly-plan', [
            'week_start' => $this->week1->toDateString(), 'values' => [$this->terraces->id => -4],
        ])->assertSessionHasErrors('values.'.$this->terraces->id);

        $this->actingAs($user)->post('/weekly-plan', ['week_start' => $this->week1->toDateString(), 'values' => [$this->terraces->id => 3]])->assertRedirect();
        $this->actingAs($user)->post('/weekly-plan', ['week_start' => $this->week1->toDateString(), 'values' => [$this->terraces->id => 3]])->assertSessionHasErrors('week_start');
        $this->actingAs($user)->get('/weekly-plan/create?week='.$this->week1->toDateString())->assertRedirect();

        $empty = $this->actingAs($user)->post('/weekly-plan', ['week_start' => $this->week2->toDateString(), 'values' => [], 'action' => 'submit']);
        $empty->assertSessionHasErrors('values');
        $this->assertSame(WeeklyPlanStatus::Draft, WeeklyPlanReport::whereDate('week_start', $this->week2)->first()->status);

        $this->actingAs($this->zoneUser)->post('/weekly-plan', ['week_start' => $this->week2->toDateString()])->assertForbidden();
    }

    public function test_draft_can_be_deleted_only_by_its_woreda(): void
    {
        $report = $this->weekly($this->woredaA, $this->woredaUserA, $this->week1, [$this->terraces->id => 1], WeeklyPlanStatus::Draft);
        app(WeeklyPlanWorkflow::class)->record($report, $this->woredaUserA, 'create', null, WeeklyPlanStatus::Draft);

        $this->actingAs($this->woredaUserB)->delete(route('weekly.destroy', $report))->assertForbidden();
        $this->actingAs($this->woredaUserA)->delete(route('weekly.destroy', $report))->assertRedirect(route('weekly.index'));
        $this->assertSame(0, WeeklyPlanReport::count());
    }

    public function test_weekly_list_is_scoped(): void
    {
        $a = $this->weekly($this->woredaA, $this->woredaUserA, $this->week1, [$this->terraces->id => 1]);
        $b = $this->weekly($this->woredaB, $this->woredaUserB, $this->week1, [$this->terraces->id => 1]);
        $link = fn ($r) => '/weekly-plan/'.$r->id;

        $this->actingAs($this->woredaUserA)->get('/weekly-plan')->assertOk()->assertSee($link($a), false)->assertDontSee($link($b), false);
        $this->actingAs($this->zoneUser)->get('/weekly-plan')->assertOk()->assertSee($link($a), false)->assertSee($link($b), false);
        $this->actingAs($this->zoneUser)->get('/weekly-plan?woreda='.$this->woredaB->id)->assertOk()->assertSee($link($b), false)->assertDontSee($link($a), false);
    }

    public function test_zone_sets_plan_and_distributes_it_to_woredas(): void
    {
        $this->actingAs($this->woredaUserA)->get('/plan')->assertForbidden();
        $this->actingAs($this->woredaUserA)->put('/plan', ['year' => $this->year])->assertForbidden();

        $this->actingAs($this->zoneUser)->get('/plan?year='.$this->year)->assertOk()->assertSee('የተፋሰስ ልማት');

        $this->actingAs($this->zoneUser)->put('/plan', [
            'year' => $this->year,
            'zone' => [$this->terraces->id => '1000', $this->milk->id => '90'],
            'woreda' => [$this->milk->id => [$this->woredaA->id => '60', $this->woredaB->id => '']],
            'distribute' => '1',
        ])->assertRedirect();

        $target = fn (PlanActivity $a, $loc) => PlanTarget::where(['plan_activity_id' => $a->id, 'location_id' => $loc->id, 'fiscal_year' => $this->year])->value('target');
        $this->assertEquals(1000, $target($this->terraces, $this->zone));
        $this->assertEquals(500, $target($this->terraces, $this->woredaA), 'blank split is shared equally');
        $this->assertEquals(500, $target($this->terraces, $this->woredaB));
        $this->assertEquals(60, $target($this->milk, $this->woredaA), 'manual split is kept');
        $this->assertNull($target($this->milk, $this->woredaB));

        $this->actingAs($this->zoneUser)->put('/plan', ['year' => $this->year, 'zone' => [$this->terraces->id => '']])->assertRedirect();
        $this->assertNull($target($this->terraces, $this->zone), 'blank removes the target');
    }

    public function test_zone_manages_goals_and_activities_only(): void
    {
        $this->actingAs($this->woredaUserA)->get('/plan/structure')->assertForbidden();
        $this->actingAs($this->woredaUserA)->post('/plan/goals', ['number' => 9, 'name_am' => 'x'])->assertForbidden();

        $this->actingAs($this->zoneUser)->get('/plan/structure')->assertOk()->assertSee('የአዝርዕት ሰብሎች');
        $this->actingAs($this->zoneUser)->post('/plan/goals', ['number' => 2, 'name_am' => 'አዲስ ግብ'])->assertRedirect();
        $goal = StrategicGoal::where('number', 2)->firstOrFail();
        $this->actingAs($this->zoneUser)->post('/plan/activities', ['strategic_goal_id' => $goal->id, 'name_am' => 'አዲስ ተግባር', 'unit' => 'ton'])->assertRedirect();
        $activity = PlanActivity::where('name_am', 'አዲስ ተግባር')->firstOrFail();
        $this->assertSame(PlanUnit::Ton, $activity->unit);

        $this->actingAs($this->zoneUser)->post('/plan/activities', ['strategic_goal_id' => $goal->id, 'name_am' => 'ስህተት', 'unit' => 'kg'])->assertSessionHasErrors('unit');

        $this->actingAs($this->zoneUser)->put(route('plan.activities.update', $activity), ['name_am' => 'አዲስ ተግባር', 'unit' => 'quintal', 'sort_order' => 3])->assertRedirect();
        $activity->refresh();
        $this->assertSame(PlanUnit::Quintal, $activity->unit);
        $this->assertFalse($activity->is_active);

        $this->actingAs($this->zoneUser)->get('/matrix')->assertOk()->assertDontSee('አዲስ ተግባር');
    }

    public function test_matrix_export_mirrors_word_layout_and_is_scoped(): void
    {
        $this->plan($this->terraces, $this->zone, 1000);
        $this->plan($this->terraces, $this->woredaA, 600);
        $this->weekly($this->woredaA, $this->woredaUserA, $this->week1, [$this->terraces->id => 150]);
        $this->weekly($this->woredaB, $this->woredaUserB, $this->week1, [$this->terraces->id => 200]);

        $download = $this->actingAs($this->zoneUser)->get('/exports/matrix?year='.$this->year);
        $download->assertOk();
        $this->assertStringContainsString('spreadsheetml', $download->headers->get('content-type'));
        $this->assertStringStartsWith('PK', $download->baseResponse->getFile()->getContent());

        $sheet = $this->readExport(new MatrixExport(app(PlanMatrix::class)->build($this->zoneUser, $this->year)));
        $this->assertSame('ተ/ቁ', $sheet->getCell('A3')->getValue());
        $this->assertSame('ዞናዊ ድምር', $sheet->getCell('E3')->getValue());
        $this->assertSame(['ዕቅድ', 'ክንውን', '%'], [$sheet->getCell('E4')->getValue(), $sheet->getCell('F4')->getValue(), $sheet->getCell('G4')->getValue()]);
        $this->assertSame('የመዋቅሮች አፈፃፀም', $sheet->getCell('H3')->getValue());
        $this->assertSame('ፎፋ', $sheet->getCell('H4')->getValue());
        $this->assertSame('ቶባ', $sheet->getCell('I4')->getValue());
        $this->assertSame('የአዝርዕት ሰብሎች', $sheet->getCell('B5')->getValue());
        $this->assertSame('የተፋሰስ ልማት', $sheet->getCell('C5')->getValue());
        $this->assertSame('ሄ/ር', $sheet->getCell('D5')->getValue());
        $this->assertEquals([1000, 350, 35], [$sheet->getCell('E5')->getValue(), $sheet->getCell('F5')->getValue(), $sheet->getCell('G5')->getValue()]);
        $this->assertEquals([150, 200], [$sheet->getCell('H5')->getValue(), $sheet->getCell('I5')->getValue()]);
        $this->assertContains('A5:A6', $sheet->getMergeCells());
        $this->assertContains('E3:G3', $sheet->getMergeCells());

        $woreda = $this->readExport(new MatrixExport(app(PlanMatrix::class)->build($this->woredaUserA, $this->year), true));
        $this->assertSame('ፎፋ: ዕቅድ', $woreda->getCell('E4')->getValue());
        $this->assertEquals([600, 150, 25], [$woreda->getCell('E5')->getValue(), $woreda->getCell('F5')->getValue(), $woreda->getCell('G5')->getValue()]);
        $this->assertNull($woreda->getCell('H4')->getValue(), 'no other woreda and no zone columns for a woreda user');

        $this->actingAs($this->woredaUserA)->get('/exports/matrix?detail=1')->assertOk();
    }

    public function test_dashboards_show_weekly_matrix_section(): void
    {
        $this->weekly($this->woredaA, $this->woredaUserA, PlanMatrix::weekStart(today())->subWeek(), [$this->terraces->id => 1]);

        $zone = $this->actingAs($this->zoneUser)->get('/dashboard');
        $zone->assertOk()->assertSee('ሳምንታዊ ዕቅድ/ክንውን ማትሪክስ')->assertSee('ቶባ');
        $this->actingAs($this->woredaUserA)->get('/dashboard')->assertOk()->assertSee('ሳምንታዊ ዕቅድ/ክንውን ማትሪክስ');
    }

    public function test_ethiopian_calendar_round_trip_and_plan_year(): void
    {
        foreach (['2025-09-11', '2026-01-01', '2026-07-07', '2026-07-08', '2024-02-29'] as $iso) {
            $date = Carbon::parse($iso);
            [$y, $m, $d] = EthiopianDate::fromGregorian((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
            $this->assertSame($iso, EthiopianDate::toGregorian($y, $m, $d)->toDateString());
        }

        $this->assertSame(2018, EthiopianDate::fiscalYear(Carbon::parse('2026-07-07')));
        $this->assertSame(2019, EthiopianDate::fiscalYear(Carbon::parse('2026-07-08')));
        [$from, $to] = EthiopianDate::fiscalYearRange(2018);
        $this->assertSame(['2025-07-08', '2026-07-07'], [$from->toDateString(), $to->toDateString()]);

        config(['plan.fiscal_year_start_month' => 1]);
        $this->assertSame(2018, EthiopianDate::fiscalYear(Carbon::parse('2025-09-11')));
        $this->assertSame('2025-09-11', EthiopianDate::fiscalYearRange(2018)[0]->toDateString());
    }

    private function readExport(MatrixExport $export)
    {
        $path = tempnam(sys_get_temp_dir(), 'matrix').'.xlsx';
        file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        return $sheet;
    }
}
