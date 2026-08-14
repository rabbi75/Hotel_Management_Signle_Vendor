<?php

declare(strict_types=1);

namespace App\Modules\Blog\Models;

use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Enums\CommentStatus;
use App\Modules\Blog\Enums\PostStatus;
use App\Modules\Blog\Services\PostRenderer;
use App\Modules\SEO\Concerns\HasSeo;
use App\Modules\SEO\Contracts\Seoable;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Searchable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A blog post.
 *
 * `body` is what the author typed and is never rendered directly; `body_html`
 * is the sanitised render produced by {@see PostRenderer}
 * and is the only form the public site emits.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $author_id
 * @property int|null $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $body
 * @property string|null $body_html
 * @property BodyFormat $body_format
 * @property PostStatus $status
 * @property CarbonImmutable|null $published_at
 * @property string|null $featured_image
 * @property int $reading_time
 * @property int $view_count
 * @property bool $is_featured
 * @property bool $allow_comments
 * @property array<string, mixed>|null $seo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $author
 * @property-read Category|null $category
 */
class Post extends Model implements Seoable
{
    /** @use HasFactory<PostFactory> */
    use BelongsToCompany, HasFactory, HasSeo, HasSlug, Searchable, SoftDeletes;

    /**
     * Slugs the public router already owns. A post claiming one would be
     * unreachable, because the admin route matches first.
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = ['posts', 'categories', 'tags', 'comments', 'category', 'tag', 'feed', 'feed.xml'];

    protected $table = 'blog_posts';

    protected $fillable = [
        'author_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'body_html',
        'body_format',
        'status',
        'published_at',
        'featured_image',
        'reading_time',
        'is_featured',
        'allow_comments',
        'seo',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            // A published post's URL is load-bearing: inbound links, shares and
            // search results all point at it. The slug is therefore generated
            // once and only ever changed by an explicit edit, never as a side
            // effect of retitling.
            ->preventOverwrite()
            ->extraScope(fn (Builder $query): Builder => $query->where('company_id', $this->getAttribute('company_id') ?? current_company_id()));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function approvedComments(): HasMany
    {
        return $this->comments()
            ->where('status', CommentStatus::Approved)
            ->whereNull('parent_id');
    }

    /**
     * Everything a reader is allowed to see: published, and not dated forward.
     *
     * A scheduled post is invisible here until `blog:publish-scheduled` promotes
     * it, and the `published_at` bound means a clock skew of a few seconds cannot
     * leak one early either.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scheduled posts whose time has come.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('status', PostStatus::Scheduled)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at instanceof CarbonImmutable
            && $this->published_at->isPast();
    }

    public function url(): ?string
    {
        return Route::has('blog.public.show') ? route('blog.public.show', $this->slug) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Seoable
    |--------------------------------------------------------------------------
    */

    public function seoTitle(): string
    {
        return $this->title;
    }

    public function seoDescription(): ?string
    {
        return $this->excerpt;
    }

    public function seoImage(): ?string
    {
        return $this->featured_image;
    }

    public function seoUrl(): ?string
    {
        return $this->url();
    }

    public function seoType(): string
    {
        return 'article';
    }

    public function seoBody(): string
    {
        return $this->body_html ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | Scout
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            // The rendered body, not the source: indexing markdown would put
            // syntax characters into the relevance calculation.
            'body' => strip_tags($this->body_html ?? ''),
            'status' => $this->status->value,
        ];
    }

    /**
     * Drafts and archived posts stay out of the index entirely — an index the
     * global search can read is an index that can leak an unpublished draft.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isPublished();
    }

    public function searchableAs(): string
    {
        return 'blog_posts';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body_format' => BodyFormat::class,
            'status' => PostStatus::class,
            'published_at' => 'immutable_datetime',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'seo' => 'array',
            'reading_time' => 'integer',
            'view_count' => 'integer',
        ];
    }
}
