<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Modules\Api\Support\OpenApiGenerator;
use Illuminate\Http\JsonResponse;

/**
 * The machine-readable description of this API. Public: a spec that requires a
 * token to read is useless to the tooling that consumes it.
 */
class OpenApiController extends Controller
{
    public function __invoke(OpenApiGenerator $generator): JsonResponse
    {
        return new JsonResponse($generator->generate(), 200, [], JSON_UNESCAPED_SLASHES);
    }
}
