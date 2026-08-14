<?php

declare(strict_types=1);

namespace App\Modules\Blog\Models;

use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * A node in the blog's category tree.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToCompany, HasFactory, HasSlug;

    protected $table = 'blog_categories';

    protected $fillable = ['parent_id', 'name', 'slug', 'description'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->preventOverwrite()
            // Without this the uniqueness probe runs withoutGlobalScopes(), so
            // one workspace naming a category "News" would push every other
            // workspace's to news-1.
            ->extraScope(fn (Builder $query): Builder => $query->where('company_id', $this->getAttribute('company_id') ?? current_company_id()));
    }

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
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    /**
     * Every id in this category's subtree, itself included — the set a category
     * archive page must query, since a reader expects a parent to show the
     * children's posts too.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        /** @var list<int> $ids */
        $ids = [$this->id];

        /** @var list<int> $frontier */
        $frontier = [$this->id];

        // Breadth-first rather than recursive so a cycle introduced by a bad
        // import cannot blow the stack; the visited set terminates it.
        while ($frontier !== []) {
            /** @var list<int> $next */
            $next = self::query()
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->map(intval(...))
                ->all();

            if ($next === []) {
                break;
            }

            $ids = [...$ids, ...$next];
            $frontier = $next;
        }

        return $ids;
    }
}
