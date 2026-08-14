<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\Api\Models\ApiToken;
use App\Modules\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records one row per public-API request.
 *
 * Bodies are opt-in (`saas.api.log_bodies`) because a response can contain
 * personal data the customer never agreed to have retained; credentials are
 * stripped unconditionally, whether bodies are enabled or not.
 */
class LogsApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('api_log_started_at', microtime(true));

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('saas.api.log_requests')) {
            return;
        }

        try {
            $this->write($request, $response);
        } catch (Throwable) {
            // Logging must never turn a served request into a failed one.
        }
    }

    protected function write(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get('api_log_started_at');
        $duration = is_float($startedAt) ? (int) round((microtime(true) - $startedAt) * 1000) : 0;

        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        $tokenId = $token instanceof ApiToken ? $token->id : null;
        $companyId = $token instanceof ApiToken ? $token->company_id : current_company_id();

        $route = $request->route();

        // The tenant is taken from the token, which may not be the workspace a
        // (session-less) request resolved, so company_id is always explicit.
        $log = new ApiRequestLog([
            'company_id' => $companyId,
            'user_id' => $user?->getAuthIdentifier(),
            'api_token_id' => $tokenId,
            'method' => $request->getMethod(),
            'path' => mb_substr($request->path(), 0, 500),
            'route_name' => $route instanceof Route ? $route->getName() : null,
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'request_body' => ApiLogPayload::request($request),
            'response_body' => ApiLogPayload::response($request, $response),
        ]);

        $log->save();
    }
}
