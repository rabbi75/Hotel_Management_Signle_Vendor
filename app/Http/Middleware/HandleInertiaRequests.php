<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Http\Resources\CompanySummaryResource;
use App\Modules\Hotel\Http\Resources\HotelSummaryResource;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Notification\Services\NotificationCenter;
use App\Modules\Platform\Actions\ImpersonateTenant;
use App\Modules\Workspace\Http\Resources\WorkspaceSummaryResource;
use App\Modules\Workspace\Services\AccessibleWorkspaces;
use App\Modules\Platform\Http\Resources\AdminResource;
use App\Modules\Platform\Models\Admin;
use App\Modules\User\Actions\StartImpersonation;
use App\Modules\User\Http\Resources\AuthenticatedUserResource;
use App\Modules\User\Models\User;
use App\Support\Branding\Branding;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

/**
 * Shared Inertia props.
 *
 * Anything here is serialised into every response, so all but the cheapest
 * values are wrapped in a lazy closure and only evaluated when a page actually
 * requests them via partial reloads.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Resolve each identity from its own guard explicitly, never the default
        // guard: in the console the default guard is `admin`, so a bare
        // $request->user() there would return an Admin and be wrapped as a tenant
        // user. The instanceof guards make the types unambiguous even if a guard
        // ever hands back the wrong model.
        $webUser = $request->user('web');
        $user = $webUser instanceof User ? $webUser : null;

        $adminUser = $request->user('admin');
        $admin = $adminUser instanceof Admin ? $adminUser : null;

        $branding = app(Branding::class)->toArray();

        return array_merge(parent::share($request), [
            // The operator-configured name, not the .env one. `branding` carries
            // the same value plus the asset URLs; `name` stays for the surfaces
            // that only ever wanted the string.
            'name' => $branding['name'],
            'branding' => $branding,

            'auth' => [
                // Resolve explicitly — an unresolved JsonResource serialises as
                // `{ data: … }`, which leaves the shell reading empty avatar/initials.
                'user' => $user === null ? null : (new AuthenticatedUserResource($user))->resolve($request),
                // The console operator, on its own guard. Present in the admin
                // panel; null in the tenant app.
                'admin' => $admin instanceof Admin ? (new AdminResource($admin))->resolve($request) : null,
                'permissions' => fn (): array => $this->permissions($user, $admin),
                'entitlements' => fn (): array => $user === null
                    ? []
                    : app(SubscriptionLimits::class)->features(),
                'company' => fn (): ?array => current_company() === null
                    ? null
                    : (new CompanySummaryResource(current_company()))->resolve($request),
                'companies' => fn (): array => $user === null
                    ? []
                    : CompanySummaryResource::collection($user->companies)->resolve($request),
                'hotel' => fn () => current_hotel() === null
                    ? null
                    : (new HotelSummaryResource(current_hotel()))->resolve($request),
                'hotels' => fn (): array => $user === null || current_company() === null
                    ? []
                    : HotelSummaryResource::collection(
                        Hotel::query()
                            ->where('is_active', true)
                            ->when(current_workspace_id(), fn ($query, $id) => $query->where('workspace_id', $id))
                            ->orderBy('name')
                            ->get(),
                    )->resolve($request),
                'workspace' => fn (): ?array => current_workspace() === null
                    ? null
                    : (new WorkspaceSummaryResource(current_workspace()))->resolve($request),
                'workspaces' => fn (): array => $user === null
                    ? []
                    : WorkspaceSummaryResource::collection(
                        app(AccessibleWorkspaces::class)->forUser($user),
                    )->resolve($request),

                // Present only while an administrator is signed in as someone
                // else. The banner reads it to offer a way back; its presence is
                // also what DenyWhileImpersonating keys on.
                'impersonator' => fn (): ?array => $this->impersonator($request),
            ],

            'singleVendor' => single_vendor(),

            'navigation' => fn (): array => $user === null ? [] : app(NavigationBuilder::class)->for($user),

            'notifications' => fn (): array => $user === null
                ? ['unread' => 0, 'items' => []]
                : app(NotificationCenter::class)->preview($user),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],

            'appearance' => $request->cookie('appearance', 'system'),
            'sidebarOpen' => $request->cookie('sidebar_state') !== 'false',

            'locale' => app()->getLocale(),
            'locales' => fn (): array => config('saas.locales'),

            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ]);
    }

    /**
     * The permission list for whichever identity owns the request: the tenant
     * user in the app, the operator in the console. The admin panel gates its UI
     * on admin-guard permissions, the tenant app on the user's.
     *
     * @return list<string>
     */
    protected function permissions(?User $user, ?Admin $admin): array
    {
        if ($admin instanceof Admin && $user === null) {
            return $admin->getAllPermissions()->pluck('name')->values()->all();
        }

        return $user?->cachedPermissionNames() ?? [];
    }

    /**
     * Whoever is standing behind an active impersonation — a tenant user (an
     * internal impersonation) or a console admin — shaped for the banner.
     *
     * @return array{name: string, return_url: string|null}|null
     */
    protected function impersonator(Request $request): ?array
    {
        $returnUrl = $request->session()->get(StartImpersonation::RETURN_KEY)
            ?? $request->session()->get(ImpersonateTenant::RETURN_KEY);
        $returnUrl = is_string($returnUrl) ? $returnUrl : null;

        $adminId = $request->session()->get(ImpersonateTenant::ADMIN_SESSION_KEY);

        if (is_int($adminId)) {
            $admin = Admin::query()->find($adminId);

            return $admin instanceof Admin
                ? ['name' => $admin->name, 'return_url' => $returnUrl]
                : null;
        }

        $userId = $request->session()->get(StartImpersonation::SESSION_KEY);

        if (is_int($userId)) {
            $impersonator = User::query()->find($userId);

            return $impersonator instanceof User
                ? ['name' => $impersonator->name, 'return_url' => $returnUrl]
                : null;
        }

        return null;
    }
}
