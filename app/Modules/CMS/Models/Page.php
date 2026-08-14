<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use App\Modules\CMS\Database\Factories\PageFactory;
use App\Modules\CMS\Enums\PageStatus;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $company_id Null for a platform-owned page: the public site, authored from the console.
 * @property int|null $parent_id
 * @property int|null $created_by
 * @property int|null $created_by_admin_id
 * @property string $title
 * @property string $slug
 * @property PageStatus $status
 * @property string $layout
 * @property array<string, mixed>|null $seo
 * @property bool $is_homepage
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id', 'created_by', 'title', 'slug', 'status', 'layout', 'seo', 'is_homepage', 'published_at',
    ];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PageBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('order');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published)
            ->where(static function (Builder $inner): void {
                $inner->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Whether a visitor with no credentials may see this page right now.
     *
     * A scheduled page whose time has come is *not* publicly live until the
     * scheduler flips its status; the check is deliberately narrow so "is this
     * public?" has exactly one answer everywhere.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === PageStatus::Published
            && ($this->published_at === null || ! $this->published_at->isFuture());
    }

    public function path(): string
    {
        return '/'.ltrim($this->slug, '/');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'seo' => 'array',
            'is_homepage' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}
