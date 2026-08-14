<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Plan entitlements
|------------------------------------------------------------------------------
|
| The closed set of feature flags a plan can grant, in the spirit of
| config/permissions.php: the plan editor renders a checkbox per key, the
| `plan.feature` middleware and the navigation gate check against it, and a key
| that is not declared here does not exist — so a typo denies rather than
| silently allows.
|
| An entitlement answers "does this workspace's plan include feature X at all",
| which is a different axis from permissions ("may this member do X") and limits
| ("how much of X"). A feature is normally gated by all three.
|
*/

return [

    'features' => [
        'ai' => 'AI assistant',
        'blog' => 'Blog',
        'cms' => 'Pages & CMS',
        'seo' => 'SEO tools',
        'chat' => 'Team chat',
        'media' => 'Media library',
        'api' => 'API access',
        'audit_log' => 'Audit log',
        'export' => 'Data export',
        'custom_roles' => 'Custom roles',

        // Hotel Management System
        'hotel_management' => 'Hotel & property management',
        'reservations' => 'Reservations & front desk',
        'housekeeping' => 'Housekeeping',
        'maintenance' => 'Maintenance',
        'hotel_reports' => 'Hotel reports & analytics',
        'online_booking' => 'Online booking engine',
        'hotel_pos' => 'Restaurant / POS',
    ],

];
