<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Web installer
    |--------------------------------------------------------------------------
    |
    | Browser-based first-run wizard. Locked once APP_INSTALLED=true.
    |
    */

    'enabled' => (bool) env('INSTALLER_ENABLED', true),

    'license' => [
        // When false, Step 3 only validates a purchase-code format (or can be skipped).
        'enabled' => (bool) env('INSTALLER_LICENSE_ENABLED', false),
        'verify_url' => env('INSTALLER_LICENSE_VERIFY_URL'),
        'product_id' => env('INSTALLER_LICENSE_PRODUCT_ID'),
        'allow_skip' => true,
    ],

    'php' => [
        'min' => '8.2.0',
        'extensions' => [
            'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'json', 'mbstring',
            'openssl', 'pcre', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'zip', 'intl',
        ],
        // At least one image extension is required.
        'image_extensions' => ['gd', 'imagick'],
    ],

    'writable' => [
        'base_path' => ['.env'],
        'directories' => [
            'storage',
            'storage/app',
            'storage/framework',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
            'public',
        ],
    ],

    'steps' => [
        'requirements',
        'permissions',
        'license',
        'database',
        'environment',
        'migrate',
        'admin',
        'finish',
    ],
];
