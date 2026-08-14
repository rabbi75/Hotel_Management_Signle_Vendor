<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Modules\Settings\Http\Middleware\CheckMaintenanceMode;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Policies\SettingsPolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;
use Illuminate\Support\Facades\Route;

class SettingsServiceProvider extends ModuleServiceProvider
{
    /**
     * Settings already declares its own /admin/settings prefix.
     */
    protected bool $panelPrefixed = false;
    protected array $policies = [
        Setting::class => SettingsPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->registerNavigation();

        // Appended rather than prepended so the session (and therefore the
        // authenticated operator who can lift maintenance) is already resolved.
        Route::pushMiddlewareToGroup('web', CheckMaintenanceMode::class);
    }

    protected function registerNavigation(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Administration', 60)->items([
                NavigationItem::make('Settings', 'admin.settings.index')
                    ->icon('settings')
                    ->permissions('platform.settings.view')
                    ->activeWhen('admin.settings.*')
                    ->order(40),
            ]),
        );
    }
}
