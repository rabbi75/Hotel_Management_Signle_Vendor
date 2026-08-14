<?php

declare(strict_types=1);

namespace App\Modules\SEO\Services;

use App\Modules\Company\Models\Company;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Writes the XML sitemap for a workspace.
 *
 * Only content a crawler is allowed to see is included: published posts and
 * pages belonging to the workspace being generated, plus the handful of static
 * routes that exist for everyone. Drafts and other workspaces' rows are not
 * filtered out at the end — they are never queried in the first place.
 */
class SitemapGenerator
{
    /**
     * Route names that are public by definition and always belong in the map.
     *
     * @var list<string>
     */
    private const STATIC_ROUTES = ['home', 'blog.public.index'];

    public function __construct(protected SeoManager $manager) {}

    /**
     * Build and write the sitemap, returning the absolute path it landed at.
     */
    public function generate(?Company $company = null): string
    {
        $sitemap = Sitemap::create();

        foreach ([...$this->staticUrls(), ...$this->postUrls($company), ...$this->pageUrls($company)] as $url) {
            $sitemap->add($url);
        }

        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $sitemap->writeToFile($path);

        return $path;
    }

    /**
     * What the dashboard shows: whether a sitemap exists, and how stale it is.
     *
     * @return array{path: string, url: string, exists: bool, generated_at: string|null, url_count: int|null, indexable: bool}
     */
    public function status(): array
    {
        $path = $this->path();
        $exists = is_file($path);
        $contents = $exists ? (string) file_get_contents($path) : '';

        return [
            'path' => $this->relativePath(),
            'url' => url($this->relativePath()),
            'exists' => $exists,
            'generated_at' => $exists ? Carbon::createFromTimestamp((int) filemtime($path))->toIso8601String() : null,
            'url_count' => $exists ? substr_count($contents, '<loc>') : null,
            'indexable' => $this->manager->indexable(),
        ];
    }

    public function path(): string
    {
        return public_path($this->relativePath());
    }

    public function relativePath(): string
    {
        return ltrim((string) config('saas.seo.sitemap_path', 'sitemap.xml'), '/');
    }

    /**
     * @return list<Url>
     */
    protected function staticUrls(): array
    {
        $urls = [];

        foreach (self::STATIC_ROUTES as $name) {
            if (! Route::has($name)) {
                continue;
            }

            $urls[] = Url::create(route($name))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                ->setPriority(1.0);
        }

        return $urls;
    }

    /**
     * @return list<Url>
     */
    protected function postUrls(?Company $company): array
    {
        if (! Route::has('blog.public.show')) {
            return [];
        }

        $query = $this->publicQuery('App\Modules\Blog\Models\Post', $company);

        if ($query === null) {
            return [];
        }

        return $query->orderByDesc('published_at')
            ->get()
            ->map(static fn (Model $post): Url => Url::create(route('blog.public.show', $post->getAttribute('slug')))
                ->setLastModificationDate(self::date($post->getAttribute('updated_at')))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.8))
            ->values()
            ->all();
    }

    /**
     * Published CMS pages, when that module is installed.
     *
     * The CMS is a separate slice of the kit and may legitimately be absent, so
     * everything about it is resolved by name and guarded.
     *
     * @return list<Url>
     */
    protected function pageUrls(?Company $company): array
    {
        if (! Route::has('cms.pages.show')) {
            return [];
        }

        $query = $this->publicQuery('App\Modules\CMS\Models\Page', $company);

        if ($query === null) {
            return [];
        }

        return $query->get()
            ->map(static fn (Model $page): Url => Url::create(route('cms.pages.show', $page->getAttribute('slug')))
                ->setLastModificationDate(self::date($page->getAttribute('updated_at')))
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.7))
            ->values()
            ->all();
    }

    /**
     * A query restricted to rows a crawler may see.
     *
     * The publication columns are consulted rather than assumed, because one of
     * the two models this is used for belongs to another module: a schema
     * change over there must not silently start publishing that module's
     * drafts through this module's sitemap. A model with no recognisable
     * publication column is skipped entirely rather than included wholesale.
     *
     * @return Builder<Model>|null
     */
    protected function publicQuery(string $class, ?Company $company): ?Builder
    {
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $class;
        $table = $instance->getTable();

        if (! Schema::hasColumn($table, 'status') || ! Schema::hasColumn($table, 'published_at')) {
            return null;
        }

        $query = $instance->newQuery()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if ($company instanceof Company && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $company->id);
        }

        return $query;
    }

    protected static function date(mixed $value): DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : Carbon::now();
    }
}
