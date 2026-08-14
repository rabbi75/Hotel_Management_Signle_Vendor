<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A super admin — tenant-side or console-side — bypasses every policy and
        // permission check. Returning null (rather than false) for everyone else
        // lets the normal gate chain continue instead of short-circuiting to a
        // denial.
        Gate::before(function (Authenticatable $actor, string $ability): ?bool {
            if ($actor instanceof User || $actor instanceof Admin) {
                return $actor->isSuperAdmin() ? true : null;
            }

            return null;
        });
    }
}
