<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the active workspace.
 *
 * The scope is a no-op when no workspace is resolved (console commands, the
 * guest area) or when {@see CurrentCompany::bypass()} is active, so cross-tenant
 * access is always an explicit, greppable decision.
 */
/**
 * @implements Scope<Model>
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(CurrentCompany::class);

        if ($tenant->isBypassed() || ! $tenant->has()) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $tenant->id());
    }

    /**
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutCompanyScope', fn (Builder $query): Builder => $query->withoutGlobalScope(self::class));

        $builder->macro('forCompany', function (Builder $query, int|Model $company): Builder {
            $id = $company instanceof Model ? $company->getKey() : $company;

            return $query->withoutGlobalScope(self::class)
                ->where($query->getModel()->qualifyColumn('company_id'), $id);
        });
    }
}
