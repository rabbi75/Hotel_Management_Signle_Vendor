<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Support\LogRedactor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the redacted, size-capped body snapshots stored on an API request log.
 */
final class ApiLogPayload
{
    /**
     * @return array<array-key, mixed>|null
     */
    public static function request(Request $request): ?array
    {
        if (! config('saas.api.log_bodies')) {
            return null;
        }

        return LogRedactor::truncate(LogRedactor::redact([
            'headers' => self::headers($request),
            'query' => $request->query(),
            'body' => $request->except(['password', 'password_confirmation']),
        ]));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function response(Request $request, Response $response): ?array
    {
        if (! config('saas.api.log_bodies')) {
            return null;
        }

        // A successful read is the one case where the body is pure customer
        // data and tells an operator nothing they could not re-fetch.
        if ($request->isMethod('GET') && $response->getStatusCode() < 300) {
            return null;
        }

        $decoded = json_decode((string) $response->getContent(), true);

        if (! is_array($decoded)) {
            return null;
        }

        return LogRedactor::truncate(LogRedactor::redact($decoded));
    }

    /**
     * @return array<string, string>
     */
    private static function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', array_map(strval(...), array_filter($values, is_string(...))));
        }

        return $headers;
    }
}
