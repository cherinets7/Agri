<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\WeeklyPlanStatus;
use App\Models\ExtensionReport;
use App\Models\Location;
use App\Models\PlanActivity;
use App\Models\PlanTarget;
use App\Models\User;
use App\Models\WeeklyPlanReport;
use App\Services\ExtensionPerformance;
use App\Services\PlanMatrix;
use App\Services\ReportWorkflow;
use App\Services\WeeklyPlanWorkflow;
use App\Support\EthiopianDate;
use Illuminate\Database\Seeder;

/** Local/demo accounts and sample reports. Password for every account: "password". */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $zone = Location::ofLevel(Level::Zone)->firstOrFail();

        User::updateOrCreate(['email' => 'zone@yem.test'], [
            'name' => 'የዞን ኃላፊ (ናሙና)', 'password' => 'password', 'role' => Role::Zone,
            'location_id' => $zone->id, 'is_active' => true,
        ]);

        $experts = [];
        foreach (Location::ofLevel(Level::Woreda)->orderBy('id')->get() as $i => $woreda) {
            $slug = strtolower(str_replace('YEM-', '', $woreda->code));
            User::updateOrCreate(['email' => "woreda.{$slug}@yem.test"], [
                'name' => "ወረዳ ኃላፊ — {$woreda->name_am}", 'password' => 'password', 'role' => Role::Woreda,
                'location_id' => $woreda->id, 'is_active' => true,
            ]);

            foreach ($woreda->children()->orderBy('id')->get() as $k => $kebele) {
                $experts[] = User::updateOrCreate(['email' => "kebele.{$slug}.".($k + 1).'@yem.test'], [
                    'name' => "ባለሙያ — {$woreda->name_am} / {$kebele->name_am}", 'password' => 'password', 'role' => Role::Kebele,
                    'location_id' => $kebele->id, 'is_active' => true,
                ]);
            }
        }

        $this->seedReports($experts);
        $this->seedWeeklyMatrix($zone);
    }

    /** @param  list<User>  $experts */
    private function seedReports(array $experts): void
    {
        if (ExtensionReport::query()->exists()) {
            return;
        }

        mt_srand(2018);
        $workflow = app(ReportWorkflow::class);
        $zoneUser = User::where('role', Role::Zone->value)->first();
        $services = array_keys(config('extension.service_types'));
        $techniques = array_keys(config('extension.techniques'));

        foreach ($experts as $n => $expert) {
            $woredaUser = User::where('role', Role::Woreda->value)->where('location_id', $expert->location->parent_id)->first();
            // every 5th expert under-performs, every 7th over-reports, every 6th misses some days
            [$min, $max] = match (true) {
                $n % 5 === 0 => [2, 4],
                $n % 7 === 3 => [11, 14],
                default => [5, 10],
            };

            for ($d = 14; $d >= 1; $d--) {
                $date = today()->subDays($d);
                if (! ExtensionPerformance::isWorkDay($date) || ($n % 6 === 1 && $d % 3 === 0)) {
                    continue;
                }

                $total = mt_rand($min, $max);
                $new = mt_rand(0, $total);
                $report = ExtensionReport::create([
                    'user_id' => $expert->id,
                    'location_id' => $expert->location_id,
                    'report_date' => $date,
                    'status' => ReportStatus::Draft,
                    'farmers_total' => $total,
                    'new_farmers' => $new,
                    'repeat_farmers' => $total - $new,
                    'female_farmers' => mt_rand(0, $total),
                    'youth_farmers' => mt_rand(0, $total),
                    'service_types' => [$services[array_rand($services)]],
                    'techniques' => [$techniques[array_rand($techniques)]],
                    'duration_minutes' => $total * mt_rand(15, 40),
                ]);
                $workflow->record($report, $expert, 'create', null, ReportStatus::Draft);
                $workflow->submit($report, $expert);

                if ($d >= 4 && $woredaUser) {
                    $workflow->verify($report, $woredaUser);
                }
                if ($d >= 8 && $zoneUser) {
                    $workflow->approve($report, $zoneUser);
                }
            }
        }
    }

    /**
     * Opening cumulative actuals from the Word matrix (one approved "week" per woreda) and an
     * equal demo split of the zone plan to the woredas so the % columns have something to show.
     */
    private function seedWeeklyMatrix(Location $zone): void
    {
        $data = PlanMatrixSeeder::data();
        $year = $data['fiscal_year'];

        if (WeeklyPlanReport::query()->where('fiscal_year', $year)->exists()) {
            return;
        }

        $woredas = Location::ofLevel(Level::Woreda)->whereIn('code', $data['woredas'])->get()->keyBy('code');
        $zoneUser = User::where('role', Role::Zone->value)->first();
        $workflow = app(WeeklyPlanWorkflow::class);
        $week = PlanMatrix::weekStart(EthiopianDate::fiscalYearRange($year)[1]);

        $activities = [];
        foreach ($data['goals'] as $g) {
            foreach ($g['activities'] as $a) {
                $activities[] = [PlanActivity::where('name_am', $a['name_am'])->firstOrFail(), $a];
            }
        }

        foreach ($activities as [$activity, $a]) {
            if ($a['zone_plan'] === null) {
                continue;
            }
            foreach ($woredas as $woreda) {
                PlanTarget::firstOrCreate(
                    ['plan_activity_id' => $activity->id, 'location_id' => $woreda->id, 'fiscal_year' => $year],
                    ['target' => round($a['zone_plan'] / max($woredas->count(), 1), 3)],
                );
            }
        }

        foreach ($data['woredas'] as $col => $code) {
            $woreda = $woredas->get($code);
            $woredaUser = $woreda ? User::where('role', Role::Woreda->value)->where('location_id', $woreda->id)->first() : null;
            if (! $woredaUser) {
                continue;
            }

            $report = WeeklyPlanReport::create([
                'woreda_id' => $woreda->id,
                'user_id' => $woredaUser->id,
                'fiscal_year' => $year,
                'week_start' => $week,
                'status' => WeeklyPlanStatus::Draft,
                'notes' => 'ናሙና፦ ከሳምንታዊ ማትሪክስ ሰነድ የተወሰደ እስከ አሁን የተከማቸ ክንውን።',
            ]);
            foreach ($activities as [$activity, $a]) {
                if ($a['actuals'][$col] !== null) {
                    $report->actuals()->create(['plan_activity_id' => $activity->id, 'value' => $a['actuals'][$col]]);
                }
            }

            $workflow->record($report, $woredaUser, 'create', null, WeeklyPlanStatus::Draft);
            $workflow->submit($report, $woredaUser);
            if ($zoneUser) {
                $workflow->approve($report, $zoneUser);
            }
        }
    }
}
