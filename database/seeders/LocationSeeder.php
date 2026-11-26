<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Woreda / structure names come from the weekly performance matrix
     * (አፈፃፀም ማትሪክስ): ሳ/ከ/አስ/ር, ደ/ሳ/ዙ/ወረዳ, ፎፋ ወረዳ, ቶባ ወረዳ.
     * The first two are abbreviations in the source and can be renamed
     * from the locations screen once the full names are confirmed.
     * Kebeles are SAMPLE placeholders until the zone supplies the real list.
     */
    public const WOREDAS = [
        ['code' => 'YEM-SKAR', 'name_am' => 'ሳ/ከ/አስ/ር', 'name_en' => 'Sa/Ke/As/R (abbrev. from matrix)'],
        ['code' => 'YEM-DSZ', 'name_am' => 'ደ/ሳ/ዙ/ወረዳ', 'name_en' => 'De/Sa/Zu woreda (abbrev. from matrix)'],
        ['code' => 'YEM-FOFA', 'name_am' => 'ፎፋ ወረዳ', 'name_en' => 'Fofa woreda'],
        ['code' => 'YEM-TOBA', 'name_am' => 'ቶባ ወረዳ', 'name_en' => 'Toba woreda'],
    ];

    public function run(): void
    {
        $zone = Location::updateOrCreate(
            ['code' => 'YEM'],
            ['level' => Level::Zone, 'name_am' => 'የየም ዞን', 'name_en' => 'Yem Zone', 'parent_id' => null],
        );

        foreach (self::WOREDAS as $w) {
            $woreda = Location::updateOrCreate(
                ['code' => $w['code']],
                ['level' => Level::Woreda, 'name_am' => $w['name_am'], 'name_en' => $w['name_en'], 'parent_id' => $zone->id],
            );

            foreach ([1, 2, 3] as $n) {
                Location::updateOrCreate(
                    ['code' => $w['code'].'-K'.$n],
                    ['level' => Level::Kebele, 'name_am' => "ናሙና ቀበሌ {$n}", 'name_en' => "Sample kebele {$n}", 'parent_id' => $woreda->id],
                );
            }
        }
    }
}
