<?php

return [
    /*
     | Daily farmers-served thresholds (applied to a day's count, or to the
     | per-reported-day average for weekly / monthly views).
     |   value <  low_below            => Low (warning)
     |   value <  target_from          => Acceptable (5-7)
     |   value <= target_to            => Target (8-10)
     |   value >  target_to            => Over (needs verification)
     */
    'low_below' => (float) env('EXT_LOW_BELOW', 5),
    'target_from' => (float) env('EXT_TARGET_FROM', 8),
    'target_to' => (float) env('EXT_TARGET_TO', 10),

    // ISO weekdays that are working days (1 = Monday ... 7 = Sunday).
    'work_days' => array_map('intval', explode(',', env('EXT_WORK_DAYS', '1,2,3,4,5'))),

    // Send email reminders alongside in-app notifications when mailer is configured.
    'email_reminders_enabled' => filter_var(env('EXT_EMAIL_REMINDERS', true), FILTER_VALIDATE_BOOL),

    // SMS reminders through the SmsGateway port (default `log` driver = stub, no paid gateway).
    'sms_reminders_enabled' => filter_var(env('EXT_SMS_REMINDERS', false), FILTER_VALIDATE_BOOL),

    'service_types' => [
        'crop' => 'ሰብል (በልግ/መኸር)',
        'irrigation' => 'መስኖ',
        'livestock' => 'እንስሳት',
        'fishery' => 'ዓሳ',
        'natural_resource' => 'ተፈጥሮ ሀብት',
        'coffee_spice' => 'ቡናና ቅመማቅመም',
        'fattening' => 'ድለባና ሙከታ',
        'other' => 'ሌላ',
    ],

    'techniques' => [
        'household_visit' => 'የቤት ለቤት ጉብኝት',
        'field_demo' => 'የማሳ ማሳያ',
        'group_training' => 'የቡድን ስልጠና',
        'field_day' => 'የመስክ ቀን',
        'advice' => 'ምክር / ውይይት',
        'other' => 'ሌላ',
    ],
];
