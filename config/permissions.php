<?php

use App\Enums\Role;

/*
 | Role capabilities. These are the DEFAULTS; the zone can switch individual capabilities on/off per
 | role from "ፈቃዶች" (/admin/permissions) — overrides are stored in the role_permissions table.
 |
 | Record-level rules (a kebele expert only edits their own drafts, a woreda only sees its own
 | kebeles, only the zone gives final approval ...) are structural and are NOT configurable here.
 | `locked` capabilities cannot be changed from the screen.
 */
return [
    'capabilities' => [
        'matrix.view' => [
            'label' => 'ሳምንታዊ ማትሪክስና ካርታ ማየት',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => true, Role::Kebele->value => false],
        ],
        'weekly.enter' => [
            'label' => 'ሳምንታዊ ክንውን (ወረዳ) ማስገባትና መላክ',
            'defaults' => [Role::Zone->value => false, Role::Woreda->value => true, Role::Kebele->value => false],
        ],
        'weekly.approve' => [
            'label' => 'ሳምንታዊ ክንውን ማጽደቅ/መመለስ',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => false, Role::Kebele->value => false],
        ],
        'kebele_weekly.enter' => [
            'label' => 'የቀበሌ ሳምንታዊ ክንውን ማስገባት (የቀበሌ ግቤት ሲነቃ)',
            'defaults' => [Role::Zone->value => false, Role::Woreda->value => false, Role::Kebele->value => true],
        ],
        'kebele_weekly.view' => [
            'label' => 'የቀበሌዎችን ሳምንታዊ ክንውን ማየት',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => true, Role::Kebele->value => false],
        ],
        'plan.manage' => [
            'label' => 'ዕቅድና ስትራተጂክ ግቦች ማስተካከል',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => false, Role::Kebele->value => false],
        ],
        'users.manage' => [
            'label' => 'ተጠቃሚዎችን ማስተዳደር',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => true, Role::Kebele->value => false],
        ],
        'locations.manage' => [
            'label' => 'አካባቢዎችን ማስተዳደር/መሰየም',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => true, Role::Kebele->value => false],
        ],
        'audit.view' => [
            'label' => 'የኦዲት መዝገብ ማየት',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => false, Role::Kebele->value => false],
        ],
        'permissions.manage' => [
            'label' => 'ፈቃዶችን ማስተዳደር',
            'defaults' => [Role::Zone->value => true, Role::Woreda->value => false, Role::Kebele->value => false],
            'locked' => true,
        ],
    ],
];
