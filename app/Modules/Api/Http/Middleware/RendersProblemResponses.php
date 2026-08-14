<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Http\Responses\ProblemResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Normalises every public-API failure into an RFC 7807 problem document.
 *
 * Rewriting the *response* rather than catching exceptions is deliberate:
 * Illuminate\Routing\Pipeline renders an exception thrown inside the controller
 * into a Response before any surrounding middleware could catch it, so a
 * try/catch here would silently miss the most common failure of all — a
 * FormRequest rejecting the payload.
 */
class RendersProblemResponses
{
    /** @var array<int, string> */
    private const TITLES = [
        400 => 'Bad request',
        401 => 'Unauthenticated',
        403 => 'Forbidden',
        404 => 'Not found',
        405 => 'Method not allowed',
        409 => 'Conflict',
        422 => 'Validation failed',
        429 => 'Too many requests',
        500 => 'Server error',
        503 => 'Service unavailable',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $exception) {
            report($exception);

            return ProblemResponse::make(500, __('Server error'), __('An unexpected error occurred.'), $request);
        }

        if ($response->getStatusCode() < 400 || $response instanceof StreamedResponse) {
            return $response;
        }

        return $this->toProblem($request, $response);
    }

    protected function toProblem(Request $request, Response $response): Response
    {
        $status = $response->getStatusCode();

        // Already a problem document (our own middleware produced it upstream).
        if (str_starts_with((string) $response->headers->get('Content-Type'), 'application/problem+json')) {
            return $response;
        }

        $decoded = json_decode((string) $response->getContent(), true);
        $body = is_array($decoded) ? $decoded : [];

        $detail = isset($body['message']) && is_string($body['message']) && $body['message'] !== ''
            ? $body['message']
            : __('The request could not be completed.');

        /** @var array<string, list<string>> $errors */
        $errors = isset($body['errors']) && is_array($body['errors']) ? $body['errors'] : [];

        return ProblemResponse::make(
            $status,
            __(self::TITLES[$status] ?? 'Request failed'),
            $detail,
            $request,
            $errors,
        );
    }
}
