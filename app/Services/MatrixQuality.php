<?php

namespace App\Services;

/**
 * Data-quality flags for the weekly matrix: documented source issues (config plan.known_issues)
 * plus automatic outlier checks on zone plan vs zone actual.
 */
class MatrixQuality
{
    /**
     * @param  array{goals:list<array>}  $matrix  output of PlanMatrix::build() (zone view)
     * @return list<array{activity:string, kind:string, message:string}>
     */
    public function flags(array $matrix): array
    {
        $known = config('plan.known_issues', []);
        $over = (float) config('plan.quality_over_ratio', 3);
        $under = (float) config('plan.quality_under_ratio', 0.01);
        $flags = [];

        foreach ($matrix['goals'] as $goal) {
            foreach ($goal['rows'] as $row) {
                $name = $row['activity']->name_am;

                if (isset($known[$name])) {
                    $flags[] = ['activity' => $name, 'kind' => 'known', 'message' => $known[$name]];
                }

                $plan = $row['zone_plan'];
                $actual = $row['zone_actual'];
                if (! $plan || $plan <= 0 || $actual === null) {
                    continue;
                }

                $ratio = $actual / $plan;
                if ($ratio > $over) {
                    $flags[] = ['activity' => $name, 'kind' => 'over', 'message' => sprintf('ክንውኑ ከዕቅዱ %s እጥፍ ይበልጣል — ዕቅድ ወይም መለኪያ (አሃድ) ይመርመር።', number_format($ratio, 1))];
                } elseif ($actual > 0 && $ratio < $under) {
                    $flags[] = ['activity' => $name, 'kind' => 'under', 'message' => 'ክንውኑ ከዕቅዱ ከ1% በታች ነው — የዕቅዱ አሃዝ ወይም መለኪያ የተሳሳተ ሊሆን ይችላል።'];
                }
            }
        }

        return $flags;
    }
}
