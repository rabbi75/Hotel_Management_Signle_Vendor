<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Discovers every module under app/Modules and registers its service provider.
 *
 * Discovery is convention based — app/Modules/{Name}/{Name}ServiceProvider.php —
 * and the resulting class list is cached to bootstrap/cache/modules.php in
 * production so directory scanning never happens on a hot request.
 */
class ModuleRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->modules() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Resolve the ordered list of module provider class names.
     *
     * @return list<class-string<ModuleServiceProvider>>
     */
    public function modules(): array
    {
        $cache = $this->app->bootstrapPath('cache/modules.php');

        if (! $this->app->isLocal() && is_file($cache)) {
            /** @var list<class-string<ModuleServiceProvider>> */
            return require $cache;
        }

        return $this->discover();
    }

    /**
     * @return list<class-string<ModuleServiceProvider>>
     */
    protected function discover(): array
    {
        $root = app_path('Modules');

        if (! is_dir($root)) {
            return [];
        }

        $providers = [];

        foreach (File::directories($root) as $directory) {
            $name = basename($directory);
            $class = "App\\Modules\\{$name}\\{$name}ServiceProvider";

            if (class_exists($class) && is_subclass_of($class, ModuleServiceProvider::class)) {
                $providers[] = $class;
            }
        }

        sort($providers);

        return $providers;
    }
}
