<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Woreda codes are stable keys (matrix data, map GeoJSON, imports); the display names are
     * editable from the locations screen (አካባቢዎች).
     *
     * `confirmed = false` marks a BEST-GUESS expansion of an abbreviation copied from the
     * performance matrix (ሳ/ከ/አስ/ር, ደ/ሳ/ዙ/ወረዳ). The original abbreviation is kept in brackets so
     * nothing is lost, and the UI shows a "ግምት — ያረጋግጡ" badge until the zone renames/confirms it.
     */
    public const WOREDAS = [
        ['code' => 'YEM-SKAR', 'name_am' => 'ሳጃ ከተማ አስተዳደር (ሳ/ከ/አስ/ር)', 'name_en' => 'Saja Town Administration (abbrev. Sa/Ke/As/R — unconfirmed)', 'confirmed' => false],
        ['code' => 'YEM-DSZ', 'name_am' => 'ደ/ሳጃ ዙሪያ ወረዳ (ደ/ሳ/ዙ/ወረዳ)', 'name_en' => 'De/Saja Zuria woreda (abbrev. De/Sa/Zu — unconfirmed)', 'confirmed' => false],
        ['code' => 'YEM-FOFA', 'name_am' => 'ፎፋ ወረዳ', 'name_en' => 'Fofa woreda', 'confirmed' => true],
        ['code' => 'YEM-TOBA', 'name_am' => 'ቶባ ወረዳ', 'name_en' => 'Toba woreda', 'confirmed' => true],
    ];

    public const KEBELES_PER_WOREDA = 3;

    public function run(): void
    {
        $zone = Location::updateOrCreate(
            ['code' => 'YEM'],
            ['level' => Level::Zone, 'name_am' => 'የየም ዞን', 'name_en' => 'Yem Zone', 'parent_id' => null],
        );

        $kebeleNo = 0;
        foreach (self::WOREDAS as $w) {
            $woreda = $this->seed($w['code'], [
                'level' => Level::Woreda,
                'name_am' => $w['name_am'],
                'name_en' => $w['name_en'],
                'parent_id' => $zone->id,
                'is_placeholder' => false,
                'name_confirmed' => $w['confirmed'],
            ]);

            // Numbered, realistic placeholders (ቀበሌ 01 ...). The real kebele list was not supplied.
            for ($n = 1; $n <= self::KEBELES_PER_WOREDA; $n++) {
                $kebeleNo++;
                $label = str_pad((string) $kebeleNo, 2, '0', STR_PAD_LEFT);
                $this->seed($w['code'].'-K'.$n, [
                    'level' => Level::Kebele,
                    'name_am' => "ቀበሌ {$label}",
                    'name_en' => "Kebele {$label} (placeholder)",
                    'parent_id' => $woreda->id,
                    'is_placeholder' => true,
                    'name_confirmed' => true,
                ]);
            }
        }
    }

    /**
     * Idempotent and safe on a live system: a row the zone already renamed/confirmed is never overwritten.
     */
    private function seed(string $code, array $attributes): Location
    {
        $location = Location::query()->where('code', $code)->first();

        if (! $location) {
            return Location::create(['code' => $code] + $attributes);
        }

        if ($location->needsReview()) {
            $location->update($attributes);
        } else {
            $location->update(['level' => $attributes['level'], 'parent_id' => $attributes['parent_id']]);
        }

        return $location;
    }
}
