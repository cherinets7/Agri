<?php

return [
    /*
     | Month (Ethiopian calendar, 1 = መስከረም ... 11 = ሐምሌ) in which the plan
     | (budget) year starts. The plan year is named after the Ethiopian year in
     | which it ends, e.g. 2018 = ሐምሌ 1, 2017 – ሰኔ 30, 2018. Set to 1 to use
     | the plain Ethiopian calendar year.
     */
    'fiscal_year_start_month' => (int) env('PLAN_FISCAL_YEAR_START_MONTH', 11),

    /*
     | Optional kebele-level weekly actuals (default OFF). When on, kebele experts enter their own
     | weekly figures (/kebele-weekly); the woreda weekly form is pre-filled with the sum of its kebeles
     | and the woreda still reviews/submits the official weekly report. Kebele figures are never
     | added to the matrix on their own, so nothing is double counted.
     */
    'kebele_weekly_entry' => filter_var(env('PLAN_KEBELE_WEEKLY_ENTRY', false), FILTER_VALIDATE_BOOL),

    /*
     | Activities whose source figures or units need confirming. Shown to zone/woreda on the matrix
     | as admin notes. Keys are the exact Amharic activity names.
     */
    'known_issues' => [
        'ባህላዊ ቀፎ' => 'ዕቅዱ በሰነዱ 578,001,806 ተብሎ ነበር — ከ"57800" ጋር "1806" (ከታች ካለው ተግባር ዕቅድ) የተጣበቀ የአሃዝ ስህተት ስለሚመስል ወደ 57,800 ተስተካክሏል። ትክክለኛውን ዕቅድ ከ"ዕቅድ" ገጽ ያረጋግጡ።',
        'የመስኖ ሰብሎች በሽታ ክትትል(በሽታ መከላከል)' => 'ዕቅዱ (1,741) ከላይኛው ረድፍ የተገለበጠ ሊሆን ይችላል፤ ክንውኑ ግን በጣም ትንሽ (31.6) ነው — ዕቅድ/መለኪያ ይረጋገጥ።',
        'ኮረሪማ ልማት' => 'መለኪያው "ቁጥር" ተብሎ ተይዟል፤ ሌሎች የቅመም ተግባራት በሄክታር ናቸው — ሄ/ር መሆን አለመሆኑን ያረጋግጡ።',
        'በመደበኛ ኮምፖስት ዝግጅት' => 'መለኪያው "ቶን" ተብሎ ተይዟል (ቨርሚ ኮምፖስት ኩ/ል ነው) — ቶን ወይስ ኩ/ል መሆኑን ያረጋግጡ።',
        'የምርጥ ዘር አቅርቦትና ጥቅም ላይ ማዋል' => 'በምንጭ ሰነዱ ክንውን ባዶ ነው — መረጃ አልደረሰም (0 ሳይሆን)።',
    ],

    // Data-quality flags: actual/plan above this ratio, or below its inverse, is flagged on the matrix.
    'quality_over_ratio' => 3.0,
    'quality_under_ratio' => 0.01,
];
