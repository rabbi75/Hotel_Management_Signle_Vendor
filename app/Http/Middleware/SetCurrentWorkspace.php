<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\CompanyMembership;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Actions\CreateDefaultWorkspace;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use App\Modules\Workspace\Enums\WorkspaceStatus;
use App\Modules\Workspace\Models\Workspace;
use App\Modules\Workspace\Models\WorkspaceMembership;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the operational workspace after the tenant (company) is bound.
 */
class SetCurrentWorkspace
{
    public function __construct(
        protected CurrentCompany $tenant,
        protected CurrentWorkspace $workspace,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            $this->workspace->forget();

            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || ! $this->tenant->has()) {
            $this->workspace->forget();
            $request->session()->forget((string) config('saas.operations.session_key'));

            return $next($request);
        }

        $sessionKey = (string) config('saas.operations.session_key');
        $storedWorkspaceId = $user->getAttributes()['current_workspace_id'] ?? null;
        $candidate = $request->session()->get($sessionKey) ?? $storedWorkspaceId;

        $resolved = $this->resolve($user, is_numeric($candidate) ? (int) $candidate : null);

        if ($resolved instanceof Workspace) {
            $this->workspace->set($resolved);
            $request->session()->put($sessionKey, $resolved->id);

            if (($user->getAttributes()['current_workspace_id'] ?? null) !== $resolved->id) {
                $user->forceFill(['current_workspace_id' => $resolved->id])->saveQuietly();
            }
        } else {
            $this->workspace->forget();
            $request->session()->forget($sessionKey);
        }

        return $next($request);
    }

    protected function resolve(User $user, ?int $candidateId): ?Workspace
    {
        $company = $this->tenant->get();

        if ($company === null) {
            return null;
        }

        if ($candidateId !== null) {
            $workspace = Workspace::query()
                ->where('company_id', $company->id)
                ->whereKey($candidateId)
                ->where('status', WorkspaceStatus::Active->value)
                ->first();

            if ($workspace instanceof Workspace && $this->canAccess($user, $workspace)) {
                return $workspace;
            }
        }

        $default = Workspace::query()
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->where('status', WorkspaceStatus::Active->value)
            ->first();

        if ($default instanceof Workspace && $this->canAccess($user, $default)) {
            return $default;
        }

        // Company owners/admins: ensure a default exists, then activate it.
        if ($this->isCompanyManager($user, $company->id)) {
            $ensured = app(CreateDefaultWorkspace::class)->handle($company, $user);

            if ($ensured->status === WorkspaceStatus::Active) {
                return $ensured;
            }

            return Workspace::query()
                ->where('company_id', $company->id)
                ->where('status', WorkspaceStatus::Active->value)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->first();
        }

        return Workspace::query()
            ->where('company_id', $company->id)
            ->where('status', WorkspaceStatus::Active->value)
            ->whereHas('members', static fn ($query) => $query
                ->whereKey($user->id)
                ->where('workspace_user.status', WorkspaceMemberStatus::Active->value))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }

    protected function canAccess(User $user, Workspace $workspace): bool
    {
        if ($user->hasRole(config('permissions.super_admin_role', 'super-admin'))) {
            return true;
        }

        if ($this->isCompanyManager($user, $workspace->company_id)) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::Active->value)
            ->exists();
    }

    protected function isCompanyManager(User $user, int $companyId): bool
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();

        return $membership !== null
            && in_array($membership->role, [CompanyRole::Owner, CompanyRole::Admin], true);
    }
}
