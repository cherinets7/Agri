<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Models\Location;
use App\Models\PlanActivity;
use App\Models\PlanTarget;
use App\Models\StrategicGoal;
use Illuminate\Database\Seeder;

/**
 * Structure + zone plan of the weekly performance matrix (agriculture-weekly-plan-report.docx):
 * 7 strategic goals, 48 activities, zone plan for 2018. Idempotent; safe in production.
 */
class PlanMatrixSeeder extends Seeder
{
    public static function data(): array
    {
        return require database_path('data/weekly-matrix-2018.php');
    }

    public function run(): void
    {
        $data = self::data();
        $zone = Location::ofLevel(Level::Zone)->first();

        foreach ($data['goals'] as $g) {
            $goal = StrategicGoal::updateOrCreate(
                ['number' => $g['number']],
                ['name_am' => $g['name_am'], 'sort_order' => $g['number'], 'is_active' => true],
            );

            foreach ($g['activities'] as $i => $a) {
                $activity = PlanActivity::firstOrCreate(
                    ['strategic_goal_id' => $goal->id, 'name_am' => $a['name_am']],
                    ['unit' => $a['unit'], 'sort_order' => $i + 1, 'is_active' => true],
                );

                if ($zone && $a['zone_plan'] !== null) {
                    // ባህላዊ ቀፎ: repair the 578001806 digit-run typo seeded earlier, but never touch a plan the zone edited.
                    PlanTarget::query()
                        ->where('plan_activity_id', $activity->id)->where('location_id', $zone->id)
                        ->where('fiscal_year', $data['fiscal_year'])->where('target', 578001806)
                        ->update(['target' => $a['zone_plan']]);

                    PlanTarget::firstOrCreate(
                        ['plan_activity_id' => $activity->id, 'location_id' => $zone->id, 'fiscal_year' => $data['fiscal_year']],
                        ['target' => $a['zone_plan']],
                    );
                }
            }
        }
    }
}
