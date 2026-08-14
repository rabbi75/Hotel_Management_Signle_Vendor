<?php

declare(strict_types=1);

namespace App\Modules\Workspace;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Models\Company;
use App\Modules\Workspace\Models\Workspace;
use App\Modules\Workspace\Policies\WorkspacePolicy;
use App\Support\Modules\ModuleServiceProvider;

class WorkspaceServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Workspace::class => WorkspacePolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->registerLimitResolvers();
    }

    protected function registerLimitResolvers(): void
    {
        SubscriptionLimits::resolveUsing(
            'workspaces',
            static fn (Company $company): int => Workspace::query()
                ->withoutCompanyScope()
                ->where('company_id', $company->id)
                ->whereNull('deleted_at')
                ->count(),
        );
    }
}
