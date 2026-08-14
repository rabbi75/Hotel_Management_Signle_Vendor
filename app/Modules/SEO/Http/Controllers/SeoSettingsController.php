<?php

declare(strict_types=1);

namespace App\Modules\SEO\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\SEO\Enums\TwitterCard;
use App\Modules\SEO\Http\Requests\UpdateSeoSettingsRequest;
use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Services\SeoManager;
use App\Modules\SEO\Services\SeoScorer;
use App\Modules\SEO\Services\SitemapGenerator;
use App\Modules\SEO\Support\SeoMetaTypes;
use App\Support\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The SEO dashboard and the workspace-wide defaults behind it.
 *
 * Defaults are written to the settings store rather than to columns of their
 * own: they are configuration, they need the same scope chain as every other
 * setting, and a new one should never require a migration.
 */
class SeoSettingsController extends Controller
{
    public function __construct(
        protected SeoManager $manager,
        protected SettingsRepository $settings,
    ) {}

    public function index(SitemapGenerator $sitemap, SeoScorer $scorer): Response
    {
        Gate::authorize('viewAny', SeoMeta::class);

        return Inertia::render('seo/index', [
            'sitemap' => $sitemap->status(),
            'defaults' => $this->manager->defaults(),
            'content' => $this->contentSummary($scorer),
            'can' => [
                'update' => Gate::allows('create', SeoMeta::class),
                'generate' => Gate::allows('generateSitemap', SeoMeta::class),
            ],
        ]);
    }

    public function edit(): Response
    {
        Gate::authorize('viewAny', SeoMeta::class);

        $defaults = $this->manager->defaults();

        return Inertia::render('seo/settings', [
            'settings' => [
                'default_title' => $defaults['title'],
                'default_description' => $defaults['description'],
                'title_suffix' => $defaults['title_suffix'],
                'title_max' => $defaults['title_max'],
                'description_max' => $defaults['description_max'],
                'default_og_image' => $defaults['og_image'],
                'twitter_handle' => $defaults['twitter_handle'],
                'robots_indexable' => $defaults['indexable'],
            ],
            'twitter_cards' => TwitterCard::options(),
            'can' => [
                'update' => Gate::allows('create', SeoMeta::class),
            ],
        ]);
    }

    public function update(UpdateSeoSettingsRequest $request, SecurityLogger $logger): RedirectResponse
    {
        $values = [
            'seo.default_title' => $request->input('default_title'),
            'seo.default_description' => $request->input('default_description'),
            'seo.title_suffix' => $request->input('title_suffix'),
            'seo.title_max' => (int) $request->input('title_max'),
            'seo.description_max' => (int) $request->input('description_max'),
            'seo.default_og_image' => $request->input('default_og_image'),
            'seo.twitter_handle' => $request->input('twitter_handle'),
            'seo.robots_indexable' => $request->boolean('robots_indexable'),
        ];

        $this->settings->setMany(
            $values,
            SettingsRepository::SCOPE_COMPANY,
            current_company_id(),
        );

        $logger->log(
            SecurityEvent::SeoSettingsChanged,
            $request->user(),
            context: ['indexable' => $request->boolean('robots_indexable')],
        );

        return back()->with(
            $request->boolean('robots_indexable') ? 'success' : 'warning',
            $request->boolean('robots_indexable')
                ? __('SEO defaults saved. This site is open to search engines.')
                : __('SEO defaults saved. This site is still marked noindex, so nothing will be crawled.'),
        );
    }

    /**
     * A score per piece of indexable content, so the dashboard can point at the
     * worst offenders instead of showing one meaningless average.
     *
     * @return list<array<string, mixed>>
     */
    protected function contentSummary(SeoScorer $scorer): array
    {
        $rows = [];

        foreach (SeoMetaTypes::allowed() as $type => $class) {
            /** @var Model $instance */
            $instance = new $class;

            $models = $instance->newQuery()
                ->with('seo')
                ->latest('updated_at')
                ->limit(25)
                ->get();

            foreach ($models as $model) {
                $meta = $model->relationLoaded('seo') ? $model->getRelation('seo') : null;
                $meta = $meta instanceof SeoMeta ? $meta : null;

                $title = $meta?->title;
                $description = $meta?->description;

                $report = $scorer->analyse([
                    'title' => $title ?? $this->attribute($model, 'title'),
                    'description' => $description ?? $this->attribute($model, 'excerpt'),
                    'canonical' => $meta?->canonical_url,
                    'slug' => $this->attribute($model, 'slug'),
                    'body_html' => $this->attribute($model, 'body_html'),
                    'keyword' => $meta?->keywords,
                ]);

                $rows[] = [
                    'type' => $type,
                    'type_label' => SeoMetaTypes::label($type),
                    'id' => (int) $model->getKey(),
                    'title' => $this->attribute($model, 'title') ?? __('Untitled'),
                    'score' => $report->score(),
                    'failed' => count($report->failures()),
                    'has_meta' => $meta instanceof SeoMeta,
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        return $rows;
    }

    protected function attribute(Model $model, string $key): ?string
    {
        $value = $model->getAttribute($key);

        return is_string($value) ? $value : null;
    }
}
