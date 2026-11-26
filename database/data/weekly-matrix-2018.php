<?php

/*
 * የሳምንታዊ ተግባር አፈፃፀም ማትሪክስ (agriculture-weekly-plan-report.docx) — የ2018 ዓ/ም ዕቅድ እና እስከ አሁን የተመዘገበ ክንውን።
 * Source: Word matrix. Units written as ditto marks (”) in the source were resolved by meaning of the activity;
 * the zone can correct any unit/plan from the plan screens. 'actuals' order = woreda codes in 'woredas'.
 * null = blank in the source (nothing reported); 0 = '-' in the source.
 *
 * DATA-QUALITY NOTES (also surfaced to admins on the matrix via config/plan.php 'known_issues'):
 *  - ባህላዊ ቀፎ: the source plan reads 578001806 (against an actual of 30,344). It is an obvious digit-run
 *    typo ("57800" + a stray "1806", the plan of the forage row further down) and is stored as 57,800.
 *    Confirm the real plan on the plan screen; PlanMatrixSeeder only corrects the untouched typo value.
 *  - Units were resolved by the meaning of the activity because the source uses ditto marks (”).
 *    Doubtful ones: ኮረሪማ ልማት (count vs hectare), በመደበኛ ኮምፖስት ዝግጅት (ton vs quintal).
 *  - Several source "zone actual" figures do not equal the sum of the four woreda columns
 *    (e.g. የ2017 ዓ/ም የበልግ ሰብሎች: 10,155.87 vs 10,716.98; የመስኖ ሰብሎች አረም: 1,558.3 vs 1,741). The app always shows the
 *    SUM of woreda actuals as the zone figure and keeps the source number in 'doc_zone_actual' for reference only.
 *  - የመስኖ ሰብሎች በሽታ ክትትል: plan 1,741 looks copied from the row above (actual 31.6).
 *  - Blank (null) actuals mean "not reported" and stay blank; they are not treated as 0.
 */

return [
    'fiscal_year' => 2018,
    'woredas' => ['YEM-SKAR', 'YEM-DSZ', 'YEM-FOFA', 'YEM-TOBA'],
    'goals' => [
        [
            'number' => 1,
            'name_am' => 'የማስፈፀም አቅም ግንባታ',
            'activities' => [
                ['name_am' => 'የማስፈፀም አቅም ግንባታ', 'unit' => 'count', 'zone_plan' => 658, 'doc_zone_actual' => null, 'actuals' => [null, null, null, null]],
            ],
        ],
        [
            'number' => 2,
            'name_am' => 'የአዝርዕት ሰብሎች ምርትና ምርታማነትን ማሳደግ',
            'activities' => [
                ['name_am' => 'የ2017 ዓ/ም የበልግ ሰብሎች ምርት አሰባሰብ', 'unit' => 'hectare', 'zone_plan' => 10155.87, 'doc_zone_actual' => 10155.87, 'actuals' => [933.062, 3203.96, 4696.25, 1883.705]],
                ['name_am' => 'የ2017ዓ/ም የመኸር ሰብሎች ምርት አሰባሰብ', 'unit' => 'hectare', 'zone_plan' => 20143.85, 'doc_zone_actual' => 20143.85, 'actuals' => [954.375, 5500.57, 7307.24, 4165.05]],
                ['name_am' => 'የ2018ዓ/ም በልግ ሰብሎች እርሻ ዝግጅት', 'unit' => 'hectare', 'zone_plan' => 10850, 'doc_zone_actual' => 3469.5, 'actuals' => [90.5, 1287, 1525, 567]],
                ['name_am' => 'ምርጥ ተሞክሮ ልየታና ቅመራ', 'unit' => 'count', 'zone_plan' => 8, 'doc_zone_actual' => 5, 'actuals' => [1, 2, 2, 0]],
                ['name_am' => 'የአ/አደር መስክ በዓል ማዘጋጀት', 'unit' => 'count', 'zone_plan' => 6, 'doc_zone_actual' => 7, 'actuals' => [2, 2, 2, 1]],
            ],
        ],
        [
            'number' => 3,
            'name_am' => 'የሆርቲካልቸር ሰብሎች ምርታማነት ማሳደግ',
            'activities' => [
                ['name_am' => 'የተለያዩ ዉሃ አማራጮችን በመጠቀም መስኖ ማልማት', 'unit' => 'hectare', 'zone_plan' => 1858, 'doc_zone_actual' => 1741, 'actuals' => [126.75, 748, 512, 354.25]],
                ['name_am' => 'የመስኖ ሰብሎች ማሳ እንክብካቤ(አረም ቁጥጥር)', 'unit' => 'hectare', 'zone_plan' => 1741, 'doc_zone_actual' => 1558.3, 'actuals' => [126.75, 748, 512, 354.25]],
                ['name_am' => 'የመስኖ ሰብሎች በሽታ ክትትል(በሽታ መከላከል)', 'unit' => 'hectare', 'zone_plan' => 1741, 'doc_zone_actual' => 31.6, 'actuals' => [6.5, 9.25, 7.6, 8.25]],
                ['name_am' => 'የመስኖ ሰብሎች ግብዓት አጠቃቀም', 'unit' => 'quintal', 'zone_plan' => 2645, 'doc_zone_actual' => 1203.75, 'actuals' => [112.42, 557.25, 292.75, 241.35]],
                ['name_am' => 'የመስኖ ልማት ዘር አጠቃቀም', 'unit' => 'quintal', 'zone_plan' => 1359.6, 'doc_zone_actual' => 1274, 'actuals' => [86.9, 512.9, 351, 259.2]],
                ['name_am' => 'የፍራፍሬ ማሳ እንክብካቤ', 'unit' => 'hectare', 'zone_plan' => 4242, 'doc_zone_actual' => 4239, 'actuals' => [699, 1600, 1250, 700]],
                ['name_am' => 'የፍራፍሬ ችግኝ ዝግጅት', 'unit' => 'count', 'zone_plan' => 526400, 'doc_zone_actual' => 19596, 'actuals' => [10840, 4482, 2130, 2144]],
                ['name_am' => 'ነባር እንሰት እንክብካቤ', 'unit' => 'hectare', 'zone_plan' => 4024, 'doc_zone_actual' => 3712, 'actuals' => [354, 1198, 1662, 498]],
                ['name_am' => 'የእንሰት ዱቦ/አምቾ ቀበራ', 'unit' => 'count', 'zone_plan' => 186800, 'doc_zone_actual' => 160340, 'actuals' => [13025, 52231, 55629.75, 39454.75]],
                ['name_am' => 'የእንሰት አጠዉልግ በሽታ መከላከል', 'unit' => 'hectare', 'zone_plan' => 950, 'doc_zone_actual' => 717.36, 'actuals' => [82.86, 315, 283, 41.5]],
            ],
        ],
        [
            'number' => 4,
            'name_am' => 'ዘላቂ የተፈጥሮ ሀብት ልማትና ጥበቃ ሥራን ማከናወን',
            'activities' => [
                ['name_am' => 'እንክብካቤ የተደረገ የደን ችግኝ', 'unit' => 'count', 'zone_plan' => 7116323, 'doc_zone_actual' => 6618178, 'actuals' => [0, 1942308, 3155385, 1520485]],
                ['name_am' => 'ለ2018 ዓ/ም ችግኝ ዝግጅት', 'unit' => 'count', 'zone_plan' => 5620998, 'doc_zone_actual' => 6693840, 'actuals' => [48840, 1850000, 2975000, 1820000]],
                ['name_am' => 'ለተፋሰስ ልማት የተለየ የሰዉ ኃይል', 'unit' => 'count', 'zone_plan' => 53651, 'doc_zone_actual' => 61064, 'actuals' => [3881, 22811, 22630, 11742]],
                ['name_am' => 'ለተፋሰስ ልማት የተለየ መሳሪያ', 'unit' => 'count', 'zone_plan' => 48200, 'doc_zone_actual' => 44612, 'actuals' => [4137, 12047, 19147, 9281]],
                ['name_am' => 'ችግኝ ጣቢያ ማቋቋም', 'unit' => 'count', 'zone_plan' => 44, 'doc_zone_actual' => 182, 'actuals' => [0, 45, 91, 46]],
                ['name_am' => 'ቨርሚ ኮምፖስት ዝግጅት', 'unit' => 'quintal', 'zone_plan' => 500, 'doc_zone_actual' => 500, 'actuals' => [51, 283, 166, 0]],
                ['name_am' => 'በመደበኛ ኮምፖስት ዝግጅት', 'unit' => 'ton', 'zone_plan' => 18144, 'doc_zone_actual' => 11200, 'actuals' => [1341, 3621, 4010, 2228]],
            ],
        ],
        [
            'number' => 5,
            'name_am' => 'ወቅቱን የጠበቀና ጥራቱን የጠበቀ የግብርና ግብዓት አቅርቦት ሥርዓት መዘርጋት',
            'activities' => [
                ['name_am' => 'የምርጥ ዘር አቅርቦትና ጥቅም ላይ ማዋል', 'unit' => 'quintal', 'zone_plan' => 3260.8, 'doc_zone_actual' => null, 'actuals' => [null, null, null, null]],
                ['name_am' => 'የአፈር ማዳበሪያ አቅርቦትና (ከመጋዘን የወጣ', 'unit' => 'quintal', 'zone_plan' => 52220, 'doc_zone_actual' => 410.5, 'actuals' => [88, 90, 178, 54.5]],
            ],
        ],
        [
            'number' => 6,
            'name_am' => 'የቡና ልማትና የቅ/ቅመም ሰብሎች ልማትና ጥበቃ ሥራዎችን ማስፋት',
            'activities' => [
                ['name_am' => 'የ2018 የቡና ችግኝ ጣቢያ ማቋቋም', 'unit' => 'count', 'zone_plan' => 30, 'doc_zone_actual' => 27, 'actuals' => [4, 11, 5, 7]],
                ['name_am' => 'የችግኝ ዝግጅት', 'unit' => 'count', 'zone_plan' => 3800000, 'doc_zone_actual' => 3160000, 'actuals' => [120000, 1440000, 710000, 890000]],
                ['name_am' => 'የሮዝመሪ ማሳ እንክብካቤ', 'unit' => 'hectare', 'zone_plan' => 80, 'doc_zone_actual' => 80, 'actuals' => [0, 4, 68, 8]],
                ['name_am' => 'በርበሬ ልማት', 'unit' => 'hectare', 'zone_plan' => 335, 'doc_zone_actual' => 334, 'actuals' => [0, 6, 89, 240]],
                ['name_am' => 'ኮረሪማ ልማት', 'unit' => 'count', 'zone_plan' => 12500, 'doc_zone_actual' => 7000, 'actuals' => [3500, 3500, 0, 0]],
            ],
        ],
        [
            'number' => 7,
            'name_am' => 'የእንስሳት ሀብት ልማት ምርትና ምርታማነትን ማሻሻል',
            'activities' => [
                ['name_am' => 'እንስሳት ዝርያ ማሻሻል', 'unit' => 'count', 'zone_plan' => 8210, 'doc_zone_actual' => 1148, 'actuals' => [164, 348, 488, 148]],
                ['name_am' => 'በዝሪያ ማሻሻል የተወለዱ ጥጆች', 'unit' => 'count', 'zone_plan' => 3507, 'doc_zone_actual' => 276, 'actuals' => [33, 38, 173, 32]],
                ['name_am' => 'የወተት ምርት', 'unit' => 'liter', 'zone_plan' => 38178, 'doc_zone_actual' => 19599.4, 'actuals' => [4965.25, 4865.15, 4972.25, 4796.75]],
                ['name_am' => 'የዳልጋ ከብት ማድለብ', 'unit' => 'count', 'zone_plan' => 96710, 'doc_zone_actual' => 57956, 'actuals' => [2210, 22136, 24690, 8815]],
                ['name_am' => 'በግ ማሞከት', 'unit' => 'count', 'zone_plan' => 78790, 'doc_zone_actual' => 46342, 'actuals' => [1428, 15373, 23685, 6856]],
                ['name_am' => 'ፍየል ማሞከት', 'unit' => 'count', 'zone_plan' => 87790, 'doc_zone_actual' => 49216, 'actuals' => [2393, 18320, 20429, 8074]],
                ['name_am' => 'የአንድ ቀን ጫጩት', 'unit' => 'count', 'zone_plan' => 120000, 'doc_zone_actual' => 32492, 'actuals' => [25342, 5794, 555, 801]],
                ['name_am' => 'የ48 ቀን ቄብና ኮክኔ', 'unit' => 'count', 'zone_plan' => 87175, 'doc_zone_actual' => 32985, 'actuals' => [14308, 8100, 7927, 2650]],
                ['name_am' => 'ባህላዊ ቀፎ', 'unit' => 'count', 'zone_plan' => 57800, 'doc_zone_actual' => 30344, 'actuals' => [3150, 11075, 7382, 8737]],
                ['name_am' => 'የማር ምርት', 'unit' => 'ton', 'zone_plan' => 600, 'doc_zone_actual' => 371, 'actuals' => [19, 129, 132, 91]],
                ['name_am' => 'የዓሣ ጉሬ ዝግጅት', 'unit' => 'count', 'zone_plan' => 36, 'doc_zone_actual' => 2, 'actuals' => [0, 0, 1, 1]],
                ['name_am' => 'የተሻሻለ መኖ ልማት', 'unit' => 'hectare', 'zone_plan' => 1806, 'doc_zone_actual' => 439.28, 'actuals' => [27.75, 112.75, 100.18, 198.6]],
                ['name_am' => 'የተሻሻለ መኖ ልማት በመስኖ', 'unit' => 'hectare', 'zone_plan' => 5.38, 'doc_zone_actual' => 0.25, 'actuals' => [0, 0.125, 0.125, 0]],
                ['name_am' => 'ሰብል ተረፈ ምርት ማዘጋጀት', 'unit' => 'ton', 'zone_plan' => 28232.3, 'doc_zone_actual' => 17344.11, 'actuals' => [250, 2845, 9596.25, 4652.86]],
                ['name_am' => 'የሰብል ተረፈ ምርት ንጥረ ምግብ ይዘት ማሻሻል', 'unit' => 'ton', 'zone_plan' => 400, 'doc_zone_actual' => 206.52, 'actuals' => [24.5, 85.75, 64.12, 32.15]],
                ['name_am' => 'ድቆሽ ማዘጋጀት', 'unit' => 'ton', 'zone_plan' => 5000, 'doc_zone_actual' => 4020.7, 'actuals' => [75.5, 1473.5, 1503.75, 967.95]],
                ['name_am' => 'የተመጣጣነ መኖ ማቅረብ', 'unit' => 'quintal', 'zone_plan' => 2000, 'doc_zone_actual' => 1025.77, 'actuals' => [700.32, 245.45, 58, 22]],
                ['name_am' => 'የእንስሳት መጠጥ ዉሃ ገንዳ ዝግጅትና ጠረጋ', 'unit' => 'count', 'zone_plan' => 700, 'doc_zone_actual' => 686, 'actuals' => [42, 214, 230, 200]],
            ],
        ],
    ],
];
