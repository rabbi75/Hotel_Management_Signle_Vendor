<?php

declare(strict_types=1);

namespace App\Modules\Media\Models;

use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A folder in the workspace's media tree.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string $path
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class MediaFolder extends Model
{
    /** @use HasFactory<MediaFolderFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['parent_id', 'name', 'slug', 'path'];

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
     * @return HasMany<MediaAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'folder_id');
    }

    public function isEmpty(): bool
    {
        return ! $this->children()->exists() && ! $this->assets()->exists();
    }

    /**
     * Whether $candidate is this folder or lives beneath it.
     *
     * The materialised path makes this a prefix test rather than a recursive
     * walk, which is what stops a move from silently creating a cycle.
     */
    public function containsFolder(self $candidate): bool
    {
        return $candidate->id === $this->id
            || str_starts_with($candidate->path.'/', $this->path.'/');
    }

    /**
     * The path this folder would have under a given parent.
     */
    public function pathUnder(?self $parent, ?string $slug = null): string
    {
        $slug = $slug ?? $this->slug;

        return $parent instanceof self ? $parent->path.'/'.$slug : $slug;
    }

    /**
     * The ancestor chain, root first, derived from the materialised path.
     *
     * @return list<string>
     */
    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path)));
    }

    public static function slugFor(string $name): string
    {
        return Str::slug($name) ?: Str::lower(Str::random(8));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
