<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the baseline security response headers.
 *
 * Content-Security-Policy is intentionally omitted here: Vite injects inline
 * module preloads in development and the correct policy differs per deployment,
 * so it belongs in the web server config where a nonce can be issued.
 */
class SecureHeaders
{
    /**
     * @var array<string, string>
     */
    protected array $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $header => $value) {
            $response->headers->set($header, $value, false);
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
