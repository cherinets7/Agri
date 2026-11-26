<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Enums\PeriodGranularity;
use App\Enums\PeriodicReportType;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\SectorType;
use App\Enums\WeeklyPlanStatus;
use App\Models\ExpertWeeklySummary;
use App\Models\ExtensionReport;
use App\Models\FarmerFeedback;
use App\Models\InputDispatch;
use App\Models\Location;
use App\Models\PeriodicReport;
use App\Models\PlanActivity;
use App\Models\PlanTarget;
use App\Models\SectorReport;
use App\Models\User;
use App\Models\WeeklyPlanReport;
use App\Services\ExpertWeeklySummaryWorkflow;
use App\Services\ExtensionPerformance;
use App\Services\PeriodicReportWorkflow;
use App\Services\PlanMatrix;
use App\Services\ReportWorkflow;
use App\Services\SectorReportWorkflow;
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
        $this->seedPeriodicReports($zone, $experts);
        $this->seedSectorReports($experts);
        $this->seedFeedbackAndSummaries($experts);
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

    /** @param  list<User>  $experts */
    private function seedPeriodicReports(Location $zone, array $experts): void
    {
        if (PeriodicReport::query()->exists()) {
            return;
        }

        $workflow = app(PeriodicReportWorkflow::class);
        $zoneUser = User::where('role', Role::Zone->value)->first();
        $date = today()->subDays(2);
        $fy = EthiopianDate::fiscalYear($date);

        if ($zoneUser) {
            foreach (Location::ofLevel(Level::Woreda)->get() as $woreda) {
                InputDispatch::create([
                    'from_location_id' => $zone->id,
                    'to_location_id' => $woreda->id,
                    'user_id' => $zoneUser->id,
                    'dispatch_date' => $date->copy()->subDays(3),
                    'fiscal_year' => $fy,
                    'input_type' => 'fertilizer',
                    'unit' => 'quintal',
                    'quantity' => 450,
                ]);
            }
        }

        $samples = [
            PeriodicReportType::CropArea->value => ['season' => 'meher', 'maize_ha' => 120.5, 'wheat_ha' => 85.0, 'barley_ha' => 42.5, 'other_ha' => 15.0, 'total_ha' => 263.0],
            PeriodicReportType::FarmerCounts->value => ['households_total' => 520, 'sowed_farmers' => 460, 'unsowed_farmers' => 60, 'female_farmers' => 140, 'youth_farmers' => 190],
            PeriodicReportType::FertilizerWarehouse->value => ['fertilizer_type' => 'combined', 'stock_quintal' => 180.0, 'distributed_quintal' => 135.0, 'remaining_quintal' => 45.0],
            PeriodicReportType::AgriStaff->value => ['sector' => 'all', 'degree_count' => 3, 'diploma_count' => 4, 'other_count' => 1, 'female_staff' => 3, 'total_staff' => 8],
            PeriodicReportType::FarmerTraining->value => ['ftc_count' => 2, 'sessions_count' => 4, 'trainees_total' => 95, 'female_trainees' => 34, 'youth_trainees' => 41, 'topic' => 'የመስመር ዘርና ማዳበሪያ አጠቃቀም'],
            PeriodicReportType::ZoneInputs->value => ['input_type' => 'fertilizer', 'unit' => 'quintal', 'woreda_dispatched' => 120.0, 'kebele_received' => 115.0, 'farmer_distributed' => 108.0, 'beneficiary_farmers' => 145],
            PeriodicReportType::SeedSupply->value => ['maize_quintal' => 24.5, 'wheat_quintal' => 18.0, 'barley_quintal' => 12.0, 'other_quintal' => 4.0, 'total_quintal' => 58.5, 'beneficiary_farmers' => 110],
        ];

        foreach (array_slice($experts, 0, 6) as $i => $expert) {
            $woredaUser = User::where('role', Role::Woreda->value)->where('location_id', $expert->location->parent_id)->first();
            foreach ($samples as $type => $payload) {
                $r = PeriodicReport::create([
                    'report_type' => $type,
                    'period_type' => PeriodGranularity::Weekly,
                    'report_date' => $date,
                    'fiscal_year' => $fy,
                    'user_id' => $expert->id,
                    'location_id' => $expert->location_id,
                    'status' => ReportStatus::Draft,
                    'payload' => $payload,
                ]);
                $workflow->record($r, $expert, 'create', null, ReportStatus::Draft);
                $workflow->submit($r, $expert);
                if ($i % 2 === 0 && $woredaUser) {
                    $workflow->verify($r, $woredaUser);
                }
                if ($i === 0 && $zoneUser) {
                    $workflow->approve($r, $zoneUser);
                }
            }
        }
    }

    /** @param  list<User>  $experts */
    private function seedSectorReports(array $experts): void
    {
        if (SectorReport::query()->exists()) {
            return;
        }

        $workflow = app(SectorReportWorkflow::class);
        $zoneUser = User::where('role', Role::Zone->value)->first();
        $date = today()->subDays(3);
        $fy = EthiopianDate::fiscalYear($date);

        $samples = [
            SectorType::Irrigation->value => ['irrigation_source' => 'river', 'water_schemes' => 2, 'irrigated_ha' => 18.5, 'beneficiaries_male' => 26, 'beneficiaries_female' => 14, 'beneficiaries_total' => 40],
            SectorType::Belg->value => ['maize_ha' => 14.0, 'vegetables_ha' => 5.5, 'wheat_ha' => 6.0, 'total_ha' => 25.5, 'expected_yield_quintal' => 240.0, 'moisture_status' => 'good'],
            SectorType::Livestock->value => ['ai_inseminations' => 15, 'calves_born' => 9, 'vaccinated_count' => 180, 'milk_liters' => 520.0, 'forage_ha' => 8.0],
            SectorType::Fishery->value => ['active_ponds' => 3, 'fingerlings_stocked' => 900, 'fish_harvest_kg' => 64.0, 'beneficiary_farmers' => 14],
            SectorType::NaturalResource->value => ['terracing_km' => 11.2, 'seedlings_planted' => 6400, 'watershed_ha' => 32.0, 'compost_tons' => 24.0, 'participants_count' => 145],
            SectorType::CoffeeSpice->value => ['coffee_seedlings' => 3200, 'coffee_ha' => 9.5, 'berbere_ha' => 4.0, 'korarima_ha' => 2.5, 'other_spice_ha' => 1.5, 'rehabilitated_ha' => 3.0],
            SectorType::DemoFattening->value => ['cattle_fattened' => 22, 'shoat_fattened' => 38, 'demo_plots_count' => 4, 'trial_participants' => 30, 'trial_outcome' => 'የመኖና የዝርያ ማሻሻያ ጥሩ ውጤት'],
        ];

        foreach (array_slice($experts, 0, 5) as $i => $expert) {
            $woredaUser = User::where('role', Role::Woreda->value)->where('location_id', $expert->location->parent_id)->first();
            foreach ($samples as $sector => $payload) {
                $r = SectorReport::create([
                    'sector' => $sector,
                    'period_type' => PeriodGranularity::Weekly,
                    'report_date' => $date,
                    'fiscal_year' => $fy,
                    'user_id' => $expert->id,
                    'location_id' => $expert->location_id,
                    'status' => ReportStatus::Draft,
                    'payload' => $payload,
                ]);
                $workflow->record($r, $expert, 'create', null, ReportStatus::Draft);
                $workflow->submit($r, $expert);
                if ($i % 2 === 0 && $woredaUser) {
                    $workflow->verify($r, $woredaUser);
                }
                if ($i === 0 && $zoneUser) {
                    $workflow->approve($r, $zoneUser);
                }
            }
        }
    }

    /** @param  list<User>  $experts */
    private function seedFeedbackAndSummaries(array $experts): void
    {
        if (FarmerFeedback::query()->exists()) {
            return;
        }

        $summaryWorkflow = app(ExpertWeeklySummaryWorkflow::class);
        $zoneUser = User::where('role', Role::Zone->value)->first();
        $weekStart = PlanMatrix::weekStart(today()->subWeek());

        foreach (array_slice($experts, 0, 5) as $i => $expert) {
            $expert->ensureFeedbackToken();
            $woredaUser = User::where('role', Role::Woreda->value)->where('location_id', $expert->location->parent_id)->first();

            FarmerFeedback::create([
                'expert_id' => $expert->id,
                'location_id' => $expert->location_id,
                'feedback_date' => $weekStart->copy()->addDays(2),
                'rating' => ($i % 2 === 0) ? 5 : 4,
                'service_type' => 'crop',
                'farmer_name' => 'አቶ በቀለ (ናሙና)',
                'comment' => 'በወቅቱ የማሳ ምክርና የዘር ድጋፍ አግኝተናል።',
                'channel' => 'qr',
            ]);

            $metrics = $summaryWorkflow->computeMetrics($expert, $weekStart);
            $summary = ExpertWeeklySummary::create([
                'user_id' => $expert->id,
                'location_id' => $expert->location_id,
                'week_start' => $weekStart,
                'fiscal_year' => EthiopianDate::fiscalYear($weekStart),
                'status' => ReportStatus::Draft,
                ...$metrics,
                'highlights' => 'የመስመር ዘር እና ማዳበሪያ አጠቃቀም ስልጠናና የቤት ለቤት ጉብኝት ተከናውኗል።',
                'challenges' => 'የትራንስፖርት እጥረት በሩቅ መንደሮች',
                'next_week_plan' => 'የቀሩ አርሶ አደሮችን መጎብኘትና ክትትል ማድረግ።',
            ]);
            $summaryWorkflow->record($summary, $expert, 'create', null, ReportStatus::Draft);
            $summaryWorkflow->submit($summary, $expert);
            if ($i % 2 === 0 && $woredaUser) {
                $summaryWorkflow->verify($summary, $woredaUser);
            }
            if ($i === 0 && $zoneUser) {
                $summaryWorkflow->approve($summary, $zoneUser);
            }
        }
    }
}
