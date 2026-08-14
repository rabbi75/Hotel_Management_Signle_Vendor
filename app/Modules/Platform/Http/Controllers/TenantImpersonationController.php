<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Actions\ImpersonateTenant;
use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Log in as tenant" from the console.
 *
 * Authorised by `platform.tenants.impersonate` on the acting Admin. The heavy
 * lifting — opening a `web` session as the owner while keeping the `admin`
 * session — lives in {@see ImpersonateTenant}.
 */
class TenantImpersonationController extends Controller
{
    public function store(Request $request, Company $company, ImpersonateTenant $impersonate): RedirectResponse
    {
        $admin = $request->user('admin');

        abort_if(! $admin instanceof Admin || $admin->cannot('platform.tenants.impersonate'), 403);

        $owner = $company->owner()->first();

        abort_if(! $owner instanceof User, 404, __('This workspace has no owner to sign in as.'));
        abort_if($owner->isSuperAdmin(), 403, __('A super admin cannot be impersonated.'));

        $impersonate->handle($request, $admin, $company, $owner, route('admin.tenants.show', $company));

        return redirect()->route('dashboard')->with('warning', __('You are now signed in as :company.', [
            'company' => $company->name,
        ]));
    }
}
