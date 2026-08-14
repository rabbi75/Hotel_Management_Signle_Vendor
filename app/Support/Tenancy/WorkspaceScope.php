<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains queries to the active operational workspace within the tenant.
 *
 * @implements Scope<Model>
 */
class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspace = app(CurrentWorkspace::class);

        if ($workspace->isBypassed() || ! $workspace->has()) {
            return;
        }

        $builder->where($model->qualifyColumn('workspace_id'), $workspace->id());
    }

    /**
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutWorkspaceScope', fn (Builder $query): Builder => $query->withoutGlobalScope(self::class));

        $builder->macro('forWorkspace', function (Builder $query, int|Model $workspace): Builder {
            $id = $workspace instanceof Model ? $workspace->getKey() : $workspace;

            return $query->withoutGlobalScope(self::class)
                ->where($query->getModel()->qualifyColumn('workspace_id'), $id);
        });
    }
}
