<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Modules\Role\Console\SyncPermissionsCommand;
use App\Modules\Role\Models\Role;
use App\Modules\Role\Policies\RolePolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class RoleServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Role::class => RolePolicy::class,
    ];

    protected function bootModule(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);
        }

        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Administration', 60)->items([
                NavigationItem::make('Roles & permissions', 'roles.index')
                    ->icon('shield-check')
                    ->permissions('roles.view')
                    ->activeWhen('roles.*')
                    ->order(10),
            ]),
        );
    }
}
