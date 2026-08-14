<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content\Concerns;

use App\Modules\Platform\Models\Admin;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ManagesPlatformContent
{
    protected function authorizeContentOperator(Request $request): void
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin && $admin->can('platform.content.manage'), 403);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    protected function platformOwned(string $model): Builder
    {
        return $model::query()->withoutGlobalScope(CompanyScope::class)->whereNull('company_id');
    }

    protected function ensurePlatformOwned(Model $model): Model
    {
        abort_unless($model->getAttribute('company_id') === null, 404);

        return $model;
    }
}
