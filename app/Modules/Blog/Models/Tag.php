<?php

declare(strict_types=1);

namespace App\Modules\Blog\Models;

use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $slug
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToCompany, HasFactory, HasSlug;

    protected $table = 'blog_tags';

    protected $fillable = ['name', 'slug'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->preventOverwrite()
            ->extraScope(fn (Builder $query): Builder => $query->where('company_id', $this->getAttribute('company_id') ?? current_company_id()));
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'blog_post_tag', 'tag_id', 'post_id');
    }
}
