<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ModuleRegistryServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    FortifyServiceProvider::class,
    TelescopeServiceProvider::class,

    // Registered last so module providers can depend on everything above.
    ModuleRegistryServiceProvider::class,
];
