<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Base provider every feature module extends.
 *
 * A module is a self-contained vertical slice under app/Modules/{Name}. This
 * provider wires the conventional pieces of that slice — routes, migrations,
 * translations, policies — so an individual module only has to declare what
 * deviates from the convention.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Policies to register, keyed by model class.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    /**
     * Middleware applied to this module's web routes, on top of the `web` group.
     *
     * @var list<string>
     */
    protected array $webMiddleware = [];

    /**
     * Middleware applied to this module's API routes, on top of the `api` group.
     *
     * @var list<string>
     */
    protected array $apiMiddleware = [];

    /**
     * Whether this module's web routes should sit under /admin in single-vendor
     * mode. Public-facing or already-prefixed modules set this to false.
     */
    protected bool $panelPrefixed = true;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerMigrations();
        $this->registerPolicies();
        $this->registerRoutes();

        $this->bootModule();
    }

    public function register(): void
    {
        $this->registerConfig();
        $this->registerModule();
    }

    /**
     * The module's short name, e.g. "User" for App\Modules\User\UserServiceProvider.
     */
    public function name(): string
    {
        return Str::of(static::class)
            ->after('App\\Modules\\')
            ->before('\\')
            ->toString();
    }

    /**
     * Absolute path to the module directory.
     */
    public function path(string $append = ''): string
    {
        $base = app_path('Modules'.DIRECTORY_SEPARATOR.$this->name());

        return $append === '' ? $base : $base.DIRECTORY_SEPARATOR.ltrim($append, '/\\');
    }

    /**
     * Hook for module-specific container bindings.
     */
    protected function registerModule(): void
    {
        //
    }

    /**
     * Hook for module-specific boot logic (events, observers, gates, macros).
     */
    protected function bootModule(): void
    {
        //
    }

    protected function registerRoutes(): void
    {
        $web = $this->path('Routes/web.php');

        if (is_file($web)) {
            $group = Route::middleware(array_merge(['web'], $this->webMiddleware));
            $prefix = $this->webRoutePrefix();

            if ($prefix !== '') {
                $group = $group->prefix($prefix);
            }

            $group->group($web);
        }

        $api = $this->path('Routes/api.php');

        if (is_file($api)) {
            Route::prefix('api')
                ->as('api.')
                ->middleware(array_merge(['api'], $this->apiMiddleware))
                ->group($api);
        }
    }

    protected function registerMigrations(): void
    {
        $path = $this->path('Database/Migrations');

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function registerTranslations(): void
    {
        $path = $this->path('Resources/lang');

        if (is_dir($path)) {
            $this->loadTranslationsFrom($path, Str::kebab($this->name()));
        }
    }

    protected function registerConfig(): void
    {
        $file = $this->path('Config/config.php');

        if (is_file($file)) {
            $this->mergeConfigFrom($file, Str::snake($this->name()));
        }
    }

    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * URL prefix for this module's authenticated web routes.
     */
    protected function webRoutePrefix(): string
    {
        if (! $this->panelPrefixed || ! single_vendor()) {
            return '';
        }

        return 'admin';
    }
}
