<?php

declare(strict_types=1);

namespace App\Modules\SEO\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SEO\Contracts\Seoable;
use App\Modules\SEO\DTOs\SeoMetaData;
use App\Modules\SEO\Http\Requests\UpdateSeoMetaRequest;
use App\Modules\SEO\Http\Resources\SeoMetaResource;
use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Services\SeoScorer;
use App\Modules\SEO\Support\SeoMetaTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads and writes the metadata of any seoable subject.
 *
 * Used by the reusable React SEO panel, which is embedded in the blog editor
 * and — when that module exists — the CMS page editor, so it deliberately knows
 * nothing about either.
 */
class SeoMetaController extends Controller
{
    public function show(Request $request, string $type, int $id, SeoScorer $scorer): JsonResponse
    {
        Gate::authorize('viewAny', SeoMeta::class);

        $model = $this->subject($type, $id);

        $meta = SeoMeta::query()
            ->where('seoable_type', $model->getMorphClass())
            ->where('seoable_id', $model->getKey())
            ->first();

        // Pulled out of the ?? expressions below: PHPStan rejects a nullsafe
        // access on the left of ??, and the fallback chain reads better named.
        $title = $meta?->title;
        $description = $meta?->description;
        $canonical = $meta?->canonical_url;

        return response()->json([
            'meta' => $meta instanceof SeoMeta
                ? (new SeoMetaResource($meta))->resolve($request)
                : SeoMetaResource::empty(),
            'report' => $scorer->analyse([
                'title' => $title ?? $model->seoTitle(),
                'description' => $description ?? $model->seoDescription(),
                'canonical' => $canonical ?? $model->seoUrl(),
                'slug' => (string) $model->getAttribute('slug'),
                'body_html' => $model->seoBody(),
                'keyword' => $meta?->keywords,
            ])->toArray(),
        ]);
    }

    public function update(UpdateSeoMetaRequest $request): RedirectResponse
    {
        $model = $this->subject((string) $request->string('type'), (int) $request->integer('id'));

        /** @var array<string, mixed> $payload */
        $payload = $request->safe()->except(['type', 'id']);

        $model->saveSeo(SeoMetaData::fromArray($payload));

        return back()->with('success', __('SEO metadata saved.'));
    }

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize('create', SeoMeta::class);

        $this->subject($type, $id)->saveSeo(null);

        return back()->with('success', __('SEO overrides removed. This page now inherits the workspace defaults.'));
    }

    /**
     * Resolve the subject, or 404.
     *
     * The lookup runs through the tenant-scoped query builder, so a subject in
     * another workspace is simply not found — there is no code path here that
     * can reach one.
     *
     * @return Model&Seoable
     */
    protected function subject(string $type, int $id): object
    {
        $model = SeoMetaTypes::resolve($type, $id);

        if (! $model instanceof Seoable) {
            throw new NotFoundHttpException;
        }

        return $model;
    }
}
