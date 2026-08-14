<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Modules\Api\Models\ApiToken;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Shared envelope and pagination behaviour for the versioned public API.
 *
 * Collections are cursor paginated: an offset page number is unstable under
 * concurrent writes, and the public API is exactly where clients iterate large
 * result sets over long periods.
 */
abstract class ApiController extends Controller
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<JsonResource>  $resource
     */
    protected function collection(Request $request, Builder $query, string $resource): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', (int) config('saas.tables.per_page')), 1), (int) config('saas.tables.max_per_page'));

        $paginator = $query->cursorPaginate($perPage)->withQueryString();

        $data = array_map(
            static fn (Model $model): array => (new $resource($model))->resolve($request),
            $paginator->items(),
        );

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'per_page' => $paginator->perPage(),
                'count' => count($data),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ],
            'links' => [
                'self' => $request->fullUrl(),
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    /**
     * @param  class-string<JsonResource>  $resource
     */
    protected function item(Request $request, Model $model, string $resource, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => (new $resource($model))->resolve($request)], $status);
    }

    /**
     * Token abilities are an additional constraint on top of the owner's
     * permissions, checked before any policy so a read-only token is rejected
     * even when its owner could perform the write.
     */
    protected function requireAbility(Request $request, string $ability): void
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $token instanceof ApiToken || ! $token->can($ability)) {
            throw new AccessDeniedHttpException(__('This token lacks the ":ability" ability.', ['ability' => $ability]));
        }
    }
}
