<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Modules\Settings\Http\Middleware\CheckMaintenanceMode;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Policies\SettingsPolicy;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;

class SettingsServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Setting::class => SettingsPolicy::class,
    ];

    protected function bootModule(): void
    {
        // No tenant navigation: every panel this module ships configures the
        // installation, so they live in the operator console's own sidebar.

        // Appended rather than prepended so the session (and therefore the
        // authenticated operator who can lift maintenance) is already resolved.
        Route::pushMiddlewareToGroup('web', CheckMaintenanceMode::class);
    }
}
