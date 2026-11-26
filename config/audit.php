<?php

use App\Models;

return [
    // Master switch for the audit trail (create / update / delete / login events).
    'enabled' => (bool) env('AUDIT_ENABLED', true),

    // Attributes never written to the log (secrets, noisy or very large values).
    'ignore_attributes' => ['password', 'remember_token', 'updated_at', 'created_at', 'feedback_token'],

    // Attributes only noted as "changed" (value not stored).
    'redact_attributes' => ['signature_data'],

    'events' => [
        'created' => 'ተፈጠረ',
        'updated' => 'ተሻሻለ',
        'deleted' => 'ተሰረዘ',
        'login' => 'መግባት',
        'logout' => 'መውጣት',
        'login_failed' => 'ያልተሳካ መግቢያ',
        'permissions_changed' => 'ፈቃድ ተቀየረ',
    ],

    'labels' => [
        Models\ExtensionReport::class => 'ዕለታዊ ሪፖርት',
        Models\PeriodicReport::class => 'ሪፖርት #2–#8',
        Models\SectorReport::class => 'የዘርፍ ቅጽ',
        Models\WeeklyPlanReport::class => 'ሳምንታዊ ክንውን (ወረዳ)',
        Models\KebeleWeeklyActual::class => 'ሳምንታዊ ክንውን (ቀበሌ)',
        Models\ExpertWeeklySummary::class => 'ሳምንታዊ ማጠቃለያ',
        Models\FarmerFeedback::class => 'የአርሶ አደር አስተያየት',
        Models\InputDispatch::class => 'የግብዓት ስርጭት',
        Models\PlanTarget::class => 'የዕቅድ ግብ',
        Models\PlanActivity::class => 'የዕቅድ ተግባር',
        Models\StrategicGoal::class => 'ስትራተጂክ ግብ',
        Models\Location::class => 'አካባቢ',
        Models\User::class => 'ተጠቃሚ',
        Models\RolePermission::class => 'የሚና ፈቃድ',
    ],
];
