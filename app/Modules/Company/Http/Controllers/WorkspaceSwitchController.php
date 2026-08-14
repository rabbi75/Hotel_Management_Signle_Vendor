<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Modules\Company\Models\Company;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentHotel;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Moves the user between the workspaces they belong to.
 *
 * The permission and navigation caches are keyed per user, not per workspace,
 * so both have to be dropped or the new workspace would render with the old
 * one's sidebar and rights.
 */
class WorkspaceSwitchController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        protected CurrentCompany $tenant,
        protected CurrentWorkspace $workspace,
        protected CurrentHotel $hotel,
        protected NavigationBuilder $navigation,
        protected SecurityLogger $security,
    ) {}

    public function __invoke(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('switchTo', $company);

        $user = $this->actor($request);

        $request->session()->put((string) config('saas.workspace.session_key'), $company->id);
        $user->forceFill(['current_company_id' => $company->id])->save();

        $this->tenant->set($company);

        // Tenant switch resets operational and property context.
        $request->session()->forget((string) config('saas.operations.session_key'));
        $request->session()->forget((string) config('saas.hotel.session_key'));
        $this->workspace->forget();
        $this->hotel->forget();
        $user->forceFill(['current_workspace_id' => null])->saveQuietly();

        $this->navigation->flushFor($user);
        $user->flushPermissionCache();

        $this->security->log(
            SecurityEvent::WorkspaceSwitched,
            $user,
            __('Switched to :company', ['company' => $company->name]),
            ['company_id' => $company->id],
        );

        return back()->with('success', __('You are now working in :company.', ['company' => $company->name]));
    }
}
