<?php

declare(strict_types=1);

namespace App\Modules\Blog\DTOs;

use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\SEO\DTOs\SeoMetaData;
use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Everything an author may set on a post.
 *
 * The absent/null distinction matters here more than anywhere else in the
 * module: a partial autosave submits only the fields the editor touched, so an
 * omitted `category_id` must mean "leave it alone" while an explicit null means
 * "uncategorise this post". {@see self::toUpdateAttributes()} is what encodes
 * that, and it is the reason the nullable properties are tracked separately in
 * {@see self::$provided}.
 */
readonly class PostData extends Data
{
    /**
     * @param  list<int>|null  $tagIds  Null leaves the existing tags untouched.
     * @param  list<string>  $provided  Request keys that were actually present.
     */
    public function __construct(
        public string $title,
        public ?string $slug = null,
        public ?string $excerpt = null,
        public ?string $body = null,
        public BodyFormat $bodyFormat = BodyFormat::Markdown,
        public PostStatus $status = PostStatus::Draft,
        public ?CarbonImmutable $publishedAt = null,
        public ?int $categoryId = null,
        public ?array $tagIds = null,
        public ?string $featuredImage = null,
        public bool $isFeatured = false,
        public bool $allowComments = true,
        public ?SeoMetaData $seo = null,
        public array $provided = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<int>|null $tagIds */
        $tagIds = $request->has('tag_ids')
            ? array_values(array_map(intval(...), (array) $request->input('tag_ids', [])))
            : null;

        $publishedAt = $request->date('published_at');

        /** @var array<string, mixed>|null $seo */
        $seo = is_array($request->input('seo')) ? $request->input('seo') : null;

        return new self(
            title: (string) $request->string('title'),
            slug: self::nullableString($request, 'slug'),
            excerpt: self::nullableString($request, 'excerpt'),
            body: $request->has('body') ? (string) $request->input('body') : null,
            bodyFormat: BodyFormat::tryFrom((string) $request->string('body_format')) ?? BodyFormat::Markdown,
            status: PostStatus::tryFrom((string) $request->string('status')) ?? PostStatus::Draft,
            publishedAt: $publishedAt === null ? null : CarbonImmutable::instance($publishedAt),
            categoryId: $request->filled('category_id') ? (int) $request->input('category_id') : null,
            tagIds: $tagIds,
            featuredImage: self::nullableString($request, 'featured_image'),
            isFeatured: $request->boolean('is_featured'),
            allowComments: $request->boolean('allow_comments', true),
            seo: $seo === null ? null : SeoMetaData::fromArray($seo),
            provided: array_map(strval(...), array_keys($request->all())),
        );
    }

    /**
     * Attributes for a fresh post. Nulls are dropped so the column defaults in
     * the migration are what a half-filled draft gets.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'body_format' => $this->bodyFormat,
            'status' => $this->status,
            'published_at' => $this->publishedAt,
            'category_id' => $this->categoryId,
            'featured_image' => $this->featuredImage,
            'is_featured' => $this->isFeatured,
            'allow_comments' => $this->allowComments,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Attributes for an edit.
     *
     * Optional fields are written back as null when the request carried the key
     * — that is how a category or a featured image is cleared — but omitted
     * entirely when it did not, so an autosave of the title cannot wipe them.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        $attributes = $this->toAttributes();

        if ($this->wasProvided('excerpt')) {
            $attributes['excerpt'] = $this->excerpt;
        }

        if ($this->wasProvided('body')) {
            $attributes['body'] = $this->body;
        }

        if ($this->wasProvided('slug')) {
            $attributes['slug'] = $this->slug;
        }

        if ($this->wasProvided('category_id')) {
            $attributes['category_id'] = $this->categoryId;
        }

        if ($this->wasProvided('featured_image')) {
            $attributes['featured_image'] = $this->featuredImage;
        }

        if ($this->wasProvided('published_at')) {
            $attributes['published_at'] = $this->publishedAt;
        }

        return $attributes;
    }

    public function wasProvided(string $key): bool
    {
        return in_array($key, $this->provided, true);
    }

    private static function nullableString(Request $request, string $key): ?string
    {
        if (! $request->has($key)) {
            return null;
        }

        $value = trim((string) $request->input($key, ''));

        return $value === '' ? null : $value;
    }
}
