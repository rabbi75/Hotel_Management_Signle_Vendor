<?php

declare(strict_types=1);

namespace App\Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Search\Contracts\SearchProvider;
use App\Modules\Search\Services\SearchAggregator;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single search endpoint.
 *
 * One route serves both consumers: the command palette fetches it as JSON, and
 * the full results page is the same payload rendered by Inertia — so the two
 * can never disagree about what a term matches.
 */
class SearchController extends Controller
{
    /**
     * Results per group for the palette, and for the full page.
     */
    protected const PALETTE_LIMIT = 5;

    protected const PAGE_LIMIT = 20;

    public function __construct(protected SearchAggregator $aggregator) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'in' => ['sometimes', 'array'],
            'in.*' => ['string'],
        ]);

        $term = (string) ($validated['q'] ?? '');

        /** @var list<string>|null $only */
        $only = isset($validated['in']) && is_array($validated['in'])
            ? array_values(array_filter($validated['in'], is_string(...)))
            : null;

        if ($this->wantsJson($request)) {
            return new JsonResponse(
                $this->aggregator->search($user, $term, self::PALETTE_LIMIT, $only)
            );
        }

        return Inertia::render('search/index', [
            'results' => $this->aggregator->search($user, $term, self::PAGE_LIMIT, $only),
            'providers' => array_values(array_map(
                static fn (SearchProvider $provider): array => [
                    'key' => $provider->key(),
                    'label' => $provider->label(),
                    'icon' => $provider->icon(),
                ],
                $this->aggregator->for($user),
            )),
            'min_length' => SearchAggregator::MIN_TERM_LENGTH,
        ]);
    }

    /**
     * An Inertia visit also sends `Accept: application/json`, so it has to be
     * excluded explicitly or the full-page route would never render.
     */
    protected function wantsJson(Request $request): bool
    {
        if ($request->header('X-Inertia')) {
            return false;
        }

        return $request->ajax() || $request->wantsJson();
    }
}
