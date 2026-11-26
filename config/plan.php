<?php

return [
    /*
     | Month (Ethiopian calendar, 1 = መስከረም ... 11 = ሐምሌ) in which the plan
     | (budget) year starts. The plan year is named after the Ethiopian year in
     | which it ends, e.g. 2018 = ሐምሌ 1, 2017 – ሰኔ 30, 2018. Set to 1 to use
     | the plain Ethiopian calendar year.
     */
    'fiscal_year_start_month' => (int) env('PLAN_FISCAL_YEAR_START_MONTH', 11),
];
