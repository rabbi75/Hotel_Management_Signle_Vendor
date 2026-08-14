<?php

declare(strict_types=1);

namespace App\Modules\Company;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\Company\Models\Department;
use App\Modules\Company\Models\Team;
use App\Modules\Company\Policies\CompanyPolicy;
use App\Modules\Company\Policies\DepartmentPolicy;
use App\Modules\Company\Policies\InvitationPolicy;
use App\Modules\Company\Policies\TeamPolicy;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationItem;
use App\Support\Navigation\NavigationSection;

class CompanyServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Company::class => CompanyPolicy::class,
        Department::class => DepartmentPolicy::class,
        Team::class => TeamPolicy::class,
        CompanyInvitation::class => InvitationPolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->app->make(NavigationBuilder::class)->register(
            NavigationSection::make('Organisation', 30)->items([
                NavigationItem::make('Organization', 'companies.index')
                    ->icon('building-2')
                    ->permissions('companies.view')
                    ->activeWhen('companies.show', 'companies.edit', 'companies.index')
                    ->order(5),

                NavigationItem::make('Members', 'companies.members.index')
                    ->icon('users-round')
                    ->permissions('companies.members.view')
                    ->activeWhen('companies.members.*')
                    ->order(10),

                NavigationItem::make('Departments', 'departments.index')
                    ->icon('network')
                    ->permissions('companies.departments.manage')
                    ->activeWhen('departments.*')
                    ->order(20),

                NavigationItem::make('Teams', 'teams.index')
                    ->icon('users')
                    ->permissions('companies.teams.manage')
                    ->activeWhen('teams.*')
                    ->order(30),
            ]),
        );
    }
}
