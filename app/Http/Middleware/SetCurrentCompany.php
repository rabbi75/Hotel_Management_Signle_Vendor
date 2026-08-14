<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the workspace every subsequent query is scoped to.
 *
 * Resolution order: the id stored in the session, then the user's last active
 * workspace, then their first membership. Membership is re-verified on every
 * request so revoking a member takes effect immediately rather than at their
 * next login.
 */
class SetCurrentCompany
{
    public function __construct(protected CurrentCompany $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Tenancy is a tenant-User concept: an Admin (or any other authenticatable
        // that is not a User) has no workspace, so there is nothing to resolve.
        if (! $user instanceof User) {
            return $next($request);
        }

        $sessionKey = (string) config('saas.workspace.session_key');
        $storedCompanyId = $user->getAttributes()['current_company_id'] ?? null;
        $candidate = $request->session()->get($sessionKey) ?? $storedCompanyId;

        $company = $this->resolve($user, is_numeric($candidate) ? (int) $candidate : null);

        if ($company instanceof Company) {
            $this->tenant->set($company);
            $request->session()->put($sessionKey, $company->id);

            if (($user->getAttributes()['current_company_id'] ?? null) !== $company->id) {
                $user->forceFill(['current_company_id' => $company->id])->saveQuietly();
            }
        } else {
            $request->session()->forget($sessionKey);
        }

        return $next($request);
    }

    /**
     * @param  User  $user
     */
    protected function resolve(mixed $user, ?int $candidateId): ?Company
    {
        if ($candidateId !== null) {
            $company = $user->companies()->whereKey($candidateId)->first();

            // A suspended workspace must not resolve — its members are locked out
            // until an operator restores it. Falling through to the first active
            // membership keeps a user with other workspaces productive rather than
            // stranding them.
            if ($company instanceof Company && $company->is_active) {
                return $company;
            }
        }

        return $user->companies()->where('companies.is_active', true)->orderBy('companies.id')->first();
    }
}
