<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Http\Controllers\Content\Concerns\ManagesPlatformContent;
use App\Modules\Platform\Http\Requests\Content\UpdatePlatformSeoSettingsRequest;
use App\Modules\SEO\Enums\TwitterCard;
use App\Modules\SEO\Models\SeoMeta;
use App\Modules\SEO\Services\SeoScorer;
use App\Modules\SEO\Services\SitemapGenerator;
use App\Modules\SEO\Support\SeoMetaTypes;
use App\Support\Settings\SettingsRepository;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSeoController extends Controller
{
    use ManagesPlatformContent;

    public function __construct(protected SettingsRepository $settings) {}

    public function index(Request $request, SitemapGenerator $sitemap, SeoScorer $scorer): Response
    {
        $this->authorizeContentOperator($request);

        return Inertia::render('seo/index', [
            'panel' => 'admin',
            'sitemap' => $sitemap->status(),
            'defaults' => $this->systemDefaults(),
            'content' => $this->contentSummary($scorer),
            'can' => ['update' => true, 'generate' => true],
        ]);
    }

    public function edit(Request $request): Response
    {
        $this->authorizeContentOperator($request);

        $defaults = $this->systemDefaults();

        return Inertia::render('seo/settings', [
            'panel' => 'admin',
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
            'can' => ['update' => true],
        ]);
    }

    public function update(UpdatePlatformSeoSettingsRequest $request, SecurityLogger $logger): RedirectResponse
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

        $this->settings->setMany($values, SettingsRepository::SCOPE_SYSTEM, null);

        $logger->log(
            SecurityEvent::SeoSettingsChanged,
            admin: $request->user('admin'),
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
     * @return array{title: string|null, description: string|null, title_suffix: string|null, title_max: int, description_max: int, og_image: string|null, twitter_handle: string|null, indexable: bool}
     */
    protected function systemDefaults(): array
    {
        $indexable = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.robots_indexable');

        return [
            'title' => $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.default_title'),
            'description' => $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.default_description'),
            'title_suffix' => $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.title_suffix')
                ?? (string) config('saas.seo.title_suffix'),
            'title_max' => (int) ($this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.title_max') ?? 60),
            'description_max' => (int) ($this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.description_max') ?? 160),
            'og_image' => $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.default_og_image')
                ?? config('saas.seo.default_og_image'),
            'twitter_handle' => $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'seo.twitter_handle')
                ?? config('saas.seo.twitter_handle'),
            'indexable' => $indexable === null
                ? (bool) config('saas.seo.robots_indexable', false)
                : filter_var($indexable, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function contentSummary(SeoScorer $scorer): array
    {
        $rows = [];

        foreach (SeoMetaTypes::allowed() as $type => $class) {
            /** @var Model $instance */
            $instance = new $class;

            $models = $instance->newQuery()
                ->withoutGlobalScope(CompanyScope::class)
                ->whereNull('company_id')
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
