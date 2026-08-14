<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Permission registry
|------------------------------------------------------------------------------
|
| The single source of truth for every permission in the application. The
| seeder syncs the database from this file, the permission matrix UI renders
| from it, and `php artisan permission:sync` prunes anything no longer listed.
|
| Naming convention: {group}.{action}. Never invent a permission at a call site
| that is not declared here — an undeclared permission silently denies.
|
*/

return [

    'super_admin_role' => 'super-admin',

    'groups' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'permissions' => [
                'dashboard.view' => 'View dashboard',
                'dashboard.customize' => 'Customise widget layout',
            ],
        ],

        'users' => [
            'label' => 'Users',
            'permissions' => [
                'users.view' => 'View users',
                'users.create' => 'Create users',
                'users.update' => 'Edit users',
                'users.delete' => 'Delete users',
                'users.suspend' => 'Suspend and restore users',
                'users.impersonate' => 'Impersonate users',
                'users.export' => 'Export users',
            ],
        ],

        'roles' => [
            'label' => 'Roles & permissions',
            'permissions' => [
                'roles.view' => 'View roles',
                'roles.create' => 'Create roles',
                'roles.update' => 'Edit roles',
                'roles.delete' => 'Delete roles',
                'roles.assign' => 'Assign roles to users',
            ],
        ],

        'companies' => [
            'label' => 'Tenants',
            'permissions' => [
                'companies.view' => 'View tenant',
                'companies.update' => 'Edit tenant settings',
                'companies.delete' => 'Delete tenant',
                'companies.members.view' => 'View members',
                'companies.members.invite' => 'Invite members',
                'companies.members.update' => 'Change member roles',
                'companies.members.remove' => 'Remove members',
                'companies.departments.manage' => 'Manage departments',
                'companies.teams.manage' => 'Manage teams',
                'companies.transfer' => 'Transfer ownership',
            ],
        ],

        'operations_workspaces' => [
            'label' => 'Operational workspaces',
            'permissions' => [
                'operations.workspaces.view' => 'View operational workspaces',
                'operations.workspaces.manage' => 'Create and manage operational workspaces',
                'operations.workspaces.switch' => 'Switch operational workspace',
            ],
        ],

        /*
         * There is deliberately no tenant `settings` group.
         *
         * Every panel the Settings module ships writes SettingsRepository's
         * *system* scope — SMTP credentials, S3 keys, the Stripe and OpenAI
         * secrets, the password policy, and maintenance mode for the whole
         * installation. Those are the operator's, so they live on the `admin`
         * guard as `platform.settings.*` below. A workspace member configures
         * their workspace, never the product it runs on.
         */

        'audit' => [
            'label' => 'Audit',
            'permissions' => [
                'audit.activity.view' => 'View activity log',
                'audit.login.view' => 'View login history',
                'audit.security.view' => 'View security log',
                'audit.export' => 'Export logs',
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'permissions' => [
                'notifications.view' => 'View notifications',
                'notifications.broadcast' => 'Send notifications to members',
            ],
        ],

        'billing' => [
            'label' => 'Billing',
            'permissions' => [
                'billing.view' => 'View subscription and invoices',
                'billing.subscribe' => 'Start or change a subscription',
                'billing.cancel' => 'Cancel a subscription',
                'billing.payment_methods.manage' => 'Manage payment methods',
                'billing.invoices.download' => 'Download invoices',
                'billing.plans.manage' => 'Create and edit plans',
                'billing.coupons.manage' => 'Create and edit coupons',
            ],
        ],

        // The platform panel sits above tenancy: these permissions govern what an
        // operator of the product may do to the workspaces that pay for it, not
        // what a member may do inside their own workspace.
        'platform' => [
            'label' => 'Platform administration',
            'permissions' => [
                'platform.access' => 'Open the platform admin panel',
                'platform.metrics.view' => 'View platform metrics',
                'platform.tenants.view' => 'View every workspace',
                'platform.tenants.manage' => 'Change a workspace plan, trial or status',
                'platform.tenants.impersonate' => 'Sign in as a workspace',
                'platform.users.manage' => 'Administer users across workspaces',
                'platform.admins.manage' => 'Create and manage platform admins',
                'platform.plans.manage' => 'Create and edit plans',
                'platform.coupons.manage' => 'Create and edit coupons',
                'platform.pages.manage' => 'Create and edit platform pages',
                'platform.content.manage' => 'Manage public site content (menus, blog, media, SEO)',
                'platform.appearance.manage' => 'Change panel theme colours',
                'platform.revenue.view' => 'View revenue and billing metrics',
                'platform.invoices.view' => 'View invoices across workspaces',
                'platform.billing.manage' => 'Retry payments, refund and extend grace',
                'platform.gateways.manage' => 'Configure payment gateways',

                // Installation-wide configuration. Each write is separately
                // permissioned because they are not equally dangerous: mail and
                // API keys are credentials, maintenance takes the product down.
                'platform.settings.view' => 'View installation settings',
                'platform.settings.general' => 'Update general and appearance settings',
                'platform.settings.mail' => 'Update mail settings',
                'platform.settings.storage' => 'Update storage settings',
                'platform.settings.security' => 'Update the security policy',
                'platform.settings.api_keys' => 'Manage third-party API keys',
                'platform.settings.ai' => 'Manage AI providers and platform credentials',
                'platform.settings.maintenance' => 'Toggle maintenance mode',
                'platform.audit.view' => 'View the platform security audit log',
                'platform.announcements.manage' => 'Broadcast announcements to tenants',
                'platform.email_templates.manage' => 'Edit lifecycle email templates',
                'platform.tickets.view' => 'View the support ticket inbox',
                'platform.tickets.manage' => 'Reply to, assign and resolve support tickets',
            ],
        ],

        'media' => [
            'label' => 'Media',
            'permissions' => [
                'media.view' => 'Browse the media library',
                'media.upload' => 'Upload files',
                'media.update' => 'Rename, move and edit files',
                'media.delete' => 'Delete files',
                'media.folders.manage' => 'Create and manage folders',
            ],
        ],

        'chat' => [
            'label' => 'Chat',
            'permissions' => [
                'chat.access' => 'Use chat',
                'chat.conversations.create' => 'Start conversations',
                'chat.messages.delete_any' => 'Delete anyone\'s message',
                'chat.attachments.upload' => 'Send attachments',
            ],
        ],

        'cms' => [
            'label' => 'CMS',
            'permissions' => [
                'cms.pages.view' => 'View pages',
                'cms.pages.create' => 'Create pages',
                'cms.pages.update' => 'Edit pages',
                'cms.pages.delete' => 'Delete pages',
                'cms.pages.publish' => 'Publish and unpublish pages',
                'cms.menus.manage' => 'Manage menus and footer',
            ],
        ],

        'blog' => [
            'label' => 'Blog',
            'permissions' => [
                'blog.posts.view' => 'View posts',
                'blog.posts.create' => 'Create posts',
                'blog.posts.update' => 'Edit any post',
                'blog.posts.delete' => 'Delete posts',
                'blog.posts.publish' => 'Publish and schedule posts',
                'blog.taxonomy.manage' => 'Manage categories and tags',
                'blog.comments.moderate' => 'Moderate comments',
            ],
        ],

        'seo' => [
            'label' => 'SEO',
            'permissions' => [
                'seo.view' => 'View SEO settings and scores',
                'seo.update' => 'Edit SEO metadata',
                'seo.sitemap.generate' => 'Regenerate the sitemap',
            ],
        ],

        'api' => [
            'label' => 'API',
            'permissions' => [
                'api.tokens.view' => 'View API tokens',
                'api.tokens.create' => 'Create API tokens',
                'api.tokens.revoke' => 'Revoke API tokens',
                'api.webhooks.manage' => 'Manage webhook endpoints',
                'api.logs.view' => 'View API request logs',
            ],
        ],

        'ai' => [
            'label' => 'AI',
            'permissions' => [
                'ai.use' => 'Run AI generations',
                'ai.history.view' => 'View generation history',
                'ai.templates.manage' => 'Manage prompt templates',
                'ai.credits.manage' => 'Adjust credit allowances',
                'ai.providers.manage' => 'Configure AI providers',
            ],
        ],

        'hotels' => [
            'label' => 'Hotels',
            'permissions' => [
                'hotels.view' => 'View hotels',
                'hotels.create' => 'Create hotels',
                'hotels.update' => 'Edit hotels',
                'hotels.delete' => 'Delete hotels',
                'hotels.switch' => 'Switch active property',
            ],
        ],

        'buildings' => [
            'label' => 'Buildings',
            'permissions' => [
                'buildings.view' => 'View buildings',
                'buildings.manage' => 'Create, edit and delete buildings',
            ],
        ],

        'floors' => [
            'label' => 'Floors',
            'permissions' => [
                'floors.view' => 'View floors',
                'floors.manage' => 'Create, edit and delete floors',
            ],
        ],

        'room_types' => [
            'label' => 'Room types',
            'permissions' => [
                'room_types.view' => 'View room types',
                'room_types.manage' => 'Create, edit and delete room types',
            ],
        ],

        'rooms' => [
            'label' => 'Rooms',
            'permissions' => [
                'rooms.view' => 'View rooms',
                'rooms.create' => 'Create rooms',
                'rooms.update' => 'Edit rooms',
                'rooms.delete' => 'Delete rooms',
                'rooms.status' => 'Change room operational status',
            ],
        ],

        'beds' => [
            'label' => 'Beds',
            'permissions' => [
                'beds.view' => 'View beds',
                'beds.manage' => 'Create, edit and delete beds',
            ],
        ],

        'facilities' => [
            'label' => 'Facilities',
            'permissions' => [
                'facilities.view' => 'View facilities',
                'facilities.manage' => 'Create, edit and delete facilities',
            ],
        ],

        'guests' => [
            'label' => 'Guests',
            'permissions' => [
                'guests.view' => 'View guests',
                'guests.create' => 'Create guests',
                'guests.update' => 'Edit guests',
                'guests.delete' => 'Delete guests',
            ],
        ],

        'reservations' => [
            'label' => 'Reservations',
            'permissions' => [
                'reservations.view' => 'View reservations',
                'reservations.create' => 'Create reservations',
                'reservations.update' => 'Edit reservations',
                'reservations.cancel' => 'Cancel reservations',
                'reservations.check_in' => 'Check guests in',
                'reservations.check_out' => 'Check guests out',
                'reservations.calendar' => 'View availability calendar',
            ],
        ],

        'hotel_services' => [
            'label' => 'Hotel services',
            'permissions' => [
                'hotel_services.view' => 'View hotel services catalog',
                'hotel_services.manage' => 'Create, edit and delete hotel services',
            ],
        ],

        'folios' => [
            'label' => 'Guest folios',
            'permissions' => [
                'folios.view' => 'View guest folios',
                'folios.manage' => 'Post charges and close folios',
                'folios.payments' => 'Record guest payments',
            ],
        ],

        'guest_invoices' => [
            'label' => 'Guest invoices',
            'permissions' => [
                'guest_invoices.view' => 'View guest invoices',
                'guest_invoices.download' => 'Download guest invoices',
            ],
        ],

        'housekeeping' => [
            'label' => 'Housekeeping',
            'permissions' => [
                'housekeeping.view' => 'View housekeeping tasks',
                'housekeeping.manage' => 'Create and update housekeeping tasks',
                'housekeeping.assign' => 'Assign housekeeping tasks',
            ],
        ],

        'maintenance' => [
            'label' => 'Maintenance',
            'permissions' => [
                'maintenance.view' => 'View maintenance work orders',
                'maintenance.create' => 'Report maintenance issues',
                'maintenance.update' => 'Edit maintenance work orders',
                'maintenance.assign' => 'Assign maintenance work orders',
                'maintenance.complete' => 'Complete maintenance work orders',
            ],
        ],

        'support' => [
            'label' => 'Support',
            'permissions' => [
                'support.view' => 'View support tickets',
                'support.create' => 'Open and reply to support tickets',
            ],
        ],

        'hotel_reports' => [
            'label' => 'Hotel reports',
            'permissions' => [
                'hotel_reports.view' => 'View hotel dashboard and reports',
            ],
        ],

        'online_booking' => [
            'label' => 'Online booking',
            'permissions' => [
                'online_booking.manage' => 'Configure online booking settings',
                'online_booking.book' => 'Create bookings via the API',
            ],
        ],

        'hotel_pos' => [
            'label' => 'Restaurant / POS',
            'permissions' => [
                'hotel_pos.view' => 'View restaurants and POS orders',
                'hotel_pos.manage' => 'Manage restaurants and POS orders',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeded roles
    |--------------------------------------------------------------------------
    |
    | `*` grants every permission in a group; `super-admin` is handled by a
    | Gate::before and therefore lists nothing.
    |
    */

    'roles' => [
        'super-admin' => [
            'label' => 'Super admin',
            'description' => 'Unrestricted access, including cross-workspace administration.',
            'permissions' => [],
        ],

        'admin' => [
            'label' => 'Administrator',
            'description' => 'Full access within a workspace.',
            'permissions' => [
                // No `settings.*`: installation configuration is the operator's,
                // and this role belongs to whoever signed up for a workspace.
                'dashboard.*', 'users.*', 'roles.*', 'companies.*', 'operations.workspaces.*', 'audit.*', 'notifications.*',
                'billing.*', 'media.*', 'chat.*', 'cms.*', 'blog.*', 'seo.*', 'api.*', 'ai.*',
                'hotels.*', 'buildings.*', 'floors.*', 'room_types.*', 'rooms.*', 'beds.*', 'facilities.*',
                'guests.*', 'reservations.*', 'hotel_services.*', 'folios.*', 'guest_invoices.*',
                'housekeeping.*', 'maintenance.*', 'hotel_reports.*',
                'online_booking.*', 'hotel_pos.*',
                'support.*',
            ],
        ],

        'manager' => [
            'label' => 'Manager',
            'description' => 'Manages people and day-to-day operations, but not billing or system settings.',
            'permissions' => [
                'dashboard.*',
                'users.view', 'users.create', 'users.update', 'users.export',
                'roles.view', 'roles.assign',
                'companies.view', 'companies.members.view', 'companies.members.invite',
                'companies.departments.manage', 'companies.teams.manage',
                'operations.workspaces.*',
                'audit.activity.view',
                'notifications.*',
                'media.*', 'chat.*', 'cms.*', 'blog.*', 'seo.view', 'seo.update',
                'ai.use', 'ai.history.view', 'ai.templates.manage',
                'billing.view',
                'hotels.*', 'buildings.*', 'floors.*', 'room_types.*', 'rooms.*', 'beds.*', 'facilities.*',
                'guests.*', 'reservations.*', 'hotel_services.*', 'folios.*', 'guest_invoices.*',
                'housekeeping.*', 'maintenance.*', 'hotel_reports.*',
                'online_booking.manage', 'online_booking.book', 'hotel_pos.*',
                'support.*',
            ],
        ],

        'member' => [
            'label' => 'Member',
            'description' => 'Standard access to shared workspace data.',
            'permissions' => [
                'dashboard.view', 'users.view', 'companies.view', 'companies.members.view', 'notifications.view',
                'operations.workspaces.view', 'operations.workspaces.switch',
                'media.view', 'media.upload',
                'chat.access', 'chat.conversations.create', 'chat.attachments.upload',
                'cms.pages.view', 'blog.posts.view', 'blog.posts.create',
                'ai.use', 'ai.history.view',
                'hotels.view', 'hotels.switch',
                'buildings.view', 'floors.view', 'room_types.view', 'rooms.view', 'beds.view', 'facilities.view',
                'guests.view', 'guests.create', 'guests.update',
                'reservations.view', 'reservations.create', 'reservations.update', 'reservations.check_in', 'reservations.check_out', 'reservations.calendar',
                'folios.view', 'folios.payments', 'guest_invoices.view', 'hotel_services.view',
                'housekeeping.view', 'maintenance.view', 'maintenance.create',
                'hotel_reports.view',
                'support.view', 'support.create',
            ],
        ],

        'guest' => [
            'label' => 'Guest',
            'description' => 'Read-only access to the dashboard.',
            'permissions' => ['dashboard.view', 'notifications.view', 'chat.access', 'media.view'],
        ],
    ],
];
