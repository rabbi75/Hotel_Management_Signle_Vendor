<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Auth\Http\Middleware\EnsureUserIsActive;
use App\Modules\Auth\Services\SocialProviderRegistry;
use App\Support\Modules\ModuleServiceProvider;

/**
 * The authentication module.
 *
 * Named for its module, not for the framework: App\Providers\AuthServiceProvider
 * is a separate, still-registered provider that owns the super-admin gate.
 */
class AuthServiceProvider extends ModuleServiceProvider
{
    /**
     * A suspended account must not be able to reach any of this module's
     * screens, even with a session that predates the suspension.
     *
     * @var list<string>
     */
    protected array $webMiddleware = [EnsureUserIsActive::class];

    protected function registerModule(): void
    {
        $this->app->singleton(SocialProviderRegistry::class);
    }
}
