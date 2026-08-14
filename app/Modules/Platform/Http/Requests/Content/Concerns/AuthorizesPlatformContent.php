<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content\Concerns;

use App\Modules\Platform\Models\Admin;
use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * The console's answer to tenant content form requests' `authorize()`.
 *
 * Tenant requests defer to policies typed to `User` and the active workspace;
 * neither exists in the admin panel. Platform ownership is asserted on any
 * route-bound model instead.
 */
trait AuthorizesPlatformContent
{
    protected function operatorMayManageContent(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin && $admin->can('platform.content.manage');
    }

    protected function isPlatformOwned(mixed $model): bool
    {
        return $model instanceof Model && $model->getAttribute('company_id') === null;
    }
}
