<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RFC 7807 `application/problem+json` error bodies.
 *
 * Every non-2xx the public API produces uses this shape, so a client only ever
 * has to write one error branch.
 */
final class ProblemResponse
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function make(int $status, string $title, string $detail, ?Request $request = null, array $errors = []): JsonResponse
    {
        $body = [
            'type' => 'https://httpstatuses.io/'.$status,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($request instanceof Request) {
            $body['instance'] = '/'.ltrim($request->path(), '/');
        }

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json']);
    }
}
