<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Http\Responses\ProblemResponse;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the bearer token and binds the request to its workspace.
 *
 * Authentication is performed here rather than through the `auth:sanctum`
 * route middleware because the framework's middleware priority list would run
 * `Authenticate` ahead of everything in this module — including the handler
 * that turns failures into RFC 7807 documents — and a public API that answers
 * 401 in two different shapes is a bug.
 *
 * The public API is session-less, so the tenant cannot come from
 * SetCurrentCompany. Taking it from the token — and refusing when the token's
 * owner is no longer a member of that workspace — is what stops a token from
 * ever reading another tenant's rows.
 */
class ResolveTokenCompany
{
    public function __construct(protected CurrentCompany $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user instanceof User) {
            return ProblemResponse::make(401, __('Unauthenticated'), __('A valid bearer token is required.'), $request);
        }

        $token = $user->currentAccessToken();

        if (! $token instanceof ApiToken) {
            return ProblemResponse::make(401, __('Unauthenticated'), __('This endpoint requires a personal access token.'), $request);
        }

        if ($token->isExpired()) {
            return ProblemResponse::make(401, __('Token expired'), __('This token has expired. Issue a new one.'), $request);
        }

        $company = Company::query()->find($token->company_id);

        if (! $company instanceof Company || ! $user->companies()->whereKey($company->id)->exists()) {
            return ProblemResponse::make(403, __('Workspace unavailable'), __('This token is not bound to a workspace you belong to.'), $request);
        }

        $this->tenant->set($company);
        Auth::shouldUse('sanctum');
        $request->setUserResolver(static fn (): User => $user);

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
