<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Platform\Actions\ImpersonateTenant;
use App\Modules\User\Actions\StartImpersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks actions that must not be taken while impersonating.
 *
 * An impersonated session can browse and reproduce a customer's experience, but
 * anything that moves money, changes credentials, or destroys the account would
 * be indistinguishable in the record from the customer doing it themselves. Those
 * routes carry this middleware and answer 403 for the duration of the
 * impersonation.
 */
class DenyWhileImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        // Either kind of impersonation — tenant-internal or admin-driven — blocks
        // the action, so both session markers are checked.
        abort_if(
            $request->session()->has(StartImpersonation::SESSION_KEY)
                || $request->session()->has(ImpersonateTenant::ADMIN_SESSION_KEY),
            403,
            __('This action is not available while impersonating a user.'),
        );

        return $next($request);
    }
}
