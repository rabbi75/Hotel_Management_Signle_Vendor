<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | Presentation defaults for the shipped starter kit. Anything a customer is
    | expected to rebrand lives here rather than being inlined in a component.
    |
    */

    'brand' => [
        'name' => env('APP_NAME', 'Hotel Management'),
        'short_name' => env('SAAS_BRAND_SHORT_NAME', 'Starter'),
        'support_email' => env('SAAS_SUPPORT_EMAIL', 'support@example.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'registration_enabled' => (bool) env('SAAS_REGISTRATION_ENABLED', true),
        'email_verification_required' => (bool) env('SAAS_EMAIL_VERIFICATION_REQUIRED', true),
        'two_factor_enabled' => (bool) env('SAAS_TWO_FACTOR_ENABLED', true),

        // Failed attempts allowed per email+IP combination before throttling.
        'max_login_attempts' => (int) env('SAAS_MAX_LOGIN_ATTEMPTS', 5),

        // How long a login-history / security-log row is retained, in days.
        'login_history_retention_days' => (int) env('SAAS_LOGIN_HISTORY_RETENTION_DAYS', 180),

        // Social providers surfaced on the login screen. A provider only shows
        // when its credentials are present in config/services.php.
        'socials' => ['google', 'github', 'facebook'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeding
    |--------------------------------------------------------------------------
    |
    | Read through config() rather than env() at the call site: once
    | `config:cache` has run — which every production deploy does — env() returns
    | null, and the seeder would silently create an account with no credentials.
    |
    */

    'seed' => [
        'admin_email' => env('SAAS_ADMIN_EMAIL', 'admin@example.com'),
        'admin_password' => env('SAAS_ADMIN_PASSWORD', 'password'),
        'admin_workspace' => env('SAAS_ADMIN_WORKSPACE', 'Acme Inc.'),
        'demo_data' => (bool) env('SAAS_SEED_DEMO_DATA', true),

        // An ordinary workspace owner, seeded alongside the demo content so the
        // tenant experience can be checked without super-admin permissions.
        'demo_tenant_email' => env('SAAS_DEMO_TENANT_EMAIL', 'tenant@example.com'),
        'demo_tenant_password' => env('SAAS_DEMO_TENANT_PASSWORD', 'password'),
        'demo_tenant_workspace' => env('SAAS_DEMO_TENANT_WORKSPACE', 'Globex LLC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform operator console
    |--------------------------------------------------------------------------
    |
    | The first admin of the operator console, seeded into the `admins` table.
    | Entirely separate from the tenant super-admin above: this account signs in
    | at /admin/login and never has a workspace.
    |
    */

    'admin' => [
        'seed_email' => env('SAAS_ADMIN_CONSOLE_EMAIL', 'admin@gmail.com'),
        'seed_password' => env('SAAS_ADMIN_CONSOLE_PASSWORD', 'password'),
        'seed_name' => env('SAAS_ADMIN_CONSOLE_NAME', 'Platform Owner'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspaces (tenancy)
    |--------------------------------------------------------------------------
    */

    'workspace' => [
        // Users may belong to many companies; this caps how many they can own.
        'max_owned_per_user' => (int) env('SAAS_MAX_OWNED_WORKSPACES', 10),

        // Invitation links expire after this many days.
        'invitation_expires_days' => (int) env('SAAS_INVITATION_EXPIRES_DAYS', 7),

        // Session key holding the active tenant (company) id.
        'session_key' => 'current_company_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational workspaces
    |--------------------------------------------------------------------------
    |
    | Inside each tenant, hotel operations are scoped to an operational
    | workspace. Distinct from the tenant session key above.
    |
    */

    'operations' => [
        'session_key' => 'current_workspace_id',
        'default_name' => env('SAAS_DEFAULT_OPERATIONAL_WORKSPACE', 'Default Workspace'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hotel / property context
    |--------------------------------------------------------------------------
    |
    | A workspace may manage multiple hotels. The active property is stored in
    | the session (and optionally on the user) the same way the active
    | workspace is — never trusted from a bare request body alone.
    |
    */

    'hotel' => [
        'session_key' => 'current_hotel_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Localisation defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'timezone' => env('SAAS_DEFAULT_TIMEZONE', 'UTC'),
        'currency' => env('SAAS_DEFAULT_CURRENCY', 'USD'),
        'locale' => env('APP_LOCALE', 'en'),
        'date_format' => 'd M Y',
        'time_format' => 'H:i',
    ],

    'locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        'bn' => ['name' => 'Bengali', 'native' => 'বাংলা', 'dir' => 'ltr'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl'],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'dir' => 'ltr'],
        'fr' => ['name' => 'French', 'native' => 'Français', 'dir' => 'ltr'],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'dir' => 'ltr'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data tables
    |--------------------------------------------------------------------------
    |
    | Server-side table defaults consumed by App\Support\DataTable.
    |
    */

    'tables' => [
        'per_page' => 25,
        'per_page_options' => [10, 25, 50, 100],
        'max_per_page' => 200,

        // Hard ceiling on rows a single synchronous export may produce; larger
        // exports are dispatched to the queue and delivered by notification.
        'max_sync_export_rows' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'disk' => env('FILESYSTEM_DISK', 'local'),
        'max_size_kb' => (int) env('SAAS_MAX_UPLOAD_KB', 20480),
        'avatar_max_size_kb' => (int) env('SAAS_AVATAR_MAX_KB', 2048),
        'image_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'],
        'document_mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'],
        'video_mimes' => ['mp4', 'webm', 'mov'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | TTLs in seconds. Keys are namespaced per tenant by the cache helper.
    |
    */

    'cache' => [
        'settings_ttl' => 3600,
        'permissions_ttl' => 3600,
        'dashboard_ttl' => 300,
        'navigation_ttl' => 900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting (requests per minute)
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'web' => 300,
        'api' => 60,
        'auth' => 10,
        'search' => 60,
        'export' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'channels' => ['database', 'mail', 'broadcast'],
        'retention_days' => (int) env('SAAS_NOTIFICATION_RETENTION_DAYS', 90),
        'bell_preview_count' => 8,

        // Seconds the per-user unread counter is cached for. Busted on every
        // write, so this is only a ceiling on how stale a missed bust can get.
        'unread_cache_ttl' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'retention_days' => (int) env('SAAS_AUDIT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing (Phase 2 — modelled here so limits can be enforced early)
    |--------------------------------------------------------------------------
    */

    'billing' => [
        'enabled' => (bool) env('SAAS_BILLING_ENABLED', false),

        // Which PaymentGateway driver is active. `manual` records subscriptions
        // and invoices without contacting a processor, so the kit is fully
        // usable — and testable — with no credentials.
        'gateway' => env('SAAS_BILLING_GATEWAY', 'manual'),
        'trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),
        'currency' => env('SAAS_DEFAULT_CURRENCY', 'USD'),

        // The plan a brand-new workspace is auto-subscribed to, on a trial driven
        // by that plan's own trial_days. Null (or a slug that does not exist)
        // disables auto-subscription — the workspace then starts with no plan and
        // is locked to the picker on first entry. Only consulted when billing is
        // enabled.
        'signup_plan' => env('SAAS_SIGNUP_PLAN', 'pro'),

        // Grace period after a failed renewal before access is revoked.
        'grace_days' => (int) env('SAAS_BILLING_GRACE_DAYS', 3),

        'invoice' => [
            'prefix' => env('SAAS_INVOICE_PREFIX', 'INV-'),
            'from_name' => env('SAAS_INVOICE_FROM_NAME', env('APP_NAME')),
            'vat_number' => env('SAAS_INVOICE_VAT_NUMBER'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media library
    |--------------------------------------------------------------------------
    */

    'media' => [
        'disk' => env('SAAS_MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
        'max_upload_kb' => (int) env('SAAS_MEDIA_MAX_KB', 51200),

        // Longest edge, in pixels, an uploaded image is downscaled to. Anything
        // larger is storage and bandwidth nobody asked for.
        'max_image_dimension' => 2560,
        'optimize' => (bool) env('SAAS_MEDIA_OPTIMIZE', true),
        'conversions' => [
            'thumb' => ['width' => 240, 'height' => 240],
            'preview' => ['width' => 960, 'height' => 960],
        ],
        'per_page' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'messages_per_page' => 50,
        'max_message_length' => 5000,
        'max_attachment_kb' => (int) env('SAAS_CHAT_MAX_ATTACHMENT_KB', 10240),

        // How long a "typing" indicator survives without a refresh, in seconds.
        'typing_ttl' => 5,

        // Window during which a sender may still delete their own message.
        'edit_window_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | CMS
    |--------------------------------------------------------------------------
    */

    'cms' => [
        // Slugs the CMS may never claim, because the application already routes
        // them. Checked on save rather than at request time.
        'reserved_slugs' => [
            'login', 'register', 'logout', 'dashboard', 'settings', 'profile',
            'users', 'roles', 'companies', 'departments', 'teams', 'search',
            'notifications', 'audit', 'billing', 'media', 'chat', 'blog', 'api',
        ],
        'preview_ttl' => 900,
        'block_types' => ['hero', 'features', 'pricing', 'testimonials', 'faq', 'contact', 'cta', 'richtext', 'gallery', 'stats'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Blog
    |--------------------------------------------------------------------------
    */

    'blog' => [
        'per_page' => 12,
        'excerpt_length' => 200,
        'words_per_minute' => 200,
        'comments_enabled' => (bool) env('SAAS_BLOG_COMMENTS', false),
        'comments_require_approval' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    */

    'seo' => [
        'title_suffix' => env('SAAS_SEO_TITLE_SUFFIX', env('APP_NAME')),
        'title_max' => 60,
        'description_max' => 160,
        'default_og_image' => env('SAAS_SEO_OG_IMAGE'),
        'twitter_handle' => env('SAAS_SEO_TWITTER'),
        'sitemap_path' => 'sitemap.xml',
        'robots_indexable' => (bool) env('SAAS_SEO_INDEXABLE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'prefix' => 'api/v1',
        'token_expiry_days' => (int) env('SAAS_API_TOKEN_DAYS', 365),
        'log_requests' => (bool) env('SAAS_API_LOG_REQUESTS', true),
        'log_retention_days' => (int) env('SAAS_API_LOG_RETENTION_DAYS', 30),

        // Response bodies can contain personal data; bodies are only retained
        // when explicitly enabled, and never for successful reads.
        'log_bodies' => (bool) env('SAAS_API_LOG_BODIES', false),

        'webhooks' => [
            'timeout' => 10,
            'max_attempts' => 5,

            // Exponential backoff between webhook retries, in seconds.
            'retry_backoff' => [60, 300, 1800, 7200],
            'signature_header' => 'X-Signature',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    |
    | Providers are resolved through a driver contract, so adding one is a new
    | class plus an entry here. Model ids are configuration, never literals in
    | application code.
    |
    */

    'ai' => [
        'enabled' => (bool) env('SAAS_AI_ENABLED', true),
        'default' => env('SAAS_AI_PROVIDER', 'anthropic'),

        'providers' => [
            'anthropic' => [
                'key' => env('ANTHROPIC_API_KEY'),

                // Model ids are exact, complete strings — never append a date
                // suffix. Opus 4.8 is the current default; `claude-sonnet-5`
                // and `claude-haiku-4-5` are the cheaper tiers.
                'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
                'base_url' => 'https://api.anthropic.com/v1',

                // Adaptive thinking + effort replaced the old fixed
                // `budget_tokens` budget, which is rejected on this model.
                'thinking' => env('ANTHROPIC_THINKING', 'adaptive'),
                'effort' => env('ANTHROPIC_EFFORT', 'high'),
            ],
            'openai' => [
                'key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_MODEL', 'gpt-4o'),
                'base_url' => 'https://api.openai.com/v1',
            ],
            'gemini' => [
                'key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            ],
            'deepseek' => [
                'key' => env('DEEPSEEK_API_KEY'),
                'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
                'base_url' => 'https://api.deepseek.com/v1',
            ],
            'grok' => [
                'key' => env('GROK_API_KEY'),
                'model' => env('GROK_MODEL', 'grok-2-latest'),
                'base_url' => 'https://api.x.ai/v1',
            ],
        ],

        'max_tokens' => (int) env('SAAS_AI_MAX_TOKENS', 4096),
        'timeout' => (int) env('SAAS_AI_TIMEOUT', 120),

        'credits' => [
            'enabled' => (bool) env('SAAS_AI_CREDITS_ENABLED', true),
            'monthly_allowance' => (int) env('SAAS_AI_MONTHLY_CREDITS', 1000),

            // Credits charged per 1,000 tokens, input and output respectively.
            'per_1k_input' => 1,
            'per_1k_output' => 3,
        ],
    ],
];
