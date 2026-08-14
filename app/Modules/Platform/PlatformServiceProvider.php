<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Platform\Services\PlatformMetrics;
use App\Support\Modules\ModuleServiceProvider;

/**
 * The platform (operator) panel.
 *
 * Registers no navigation: the admin shell has a fixed sidebar of its own
 * rather than a module-contributed one, because its contents must not depend on
 * — or be cached alongside — a tenant's plan and permissions.
 */
class PlatformServiceProvider extends ModuleServiceProvider
{
    /**
     * Platform routes already live under /admin.
     */
    protected bool $panelPrefixed = false;
    protected function registerModule(): void
    {
        $this->app->singleton(PlatformMetrics::class);
    }
}
