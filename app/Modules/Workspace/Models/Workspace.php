<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Workspace\Enums\WorkspaceMemberRole;
use App\Modules\Workspace\Enums\WorkspaceMemberStatus;
use App\Modules\Workspace\Enums\WorkspaceStatus;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Operational workspace inside a tenant (company).
 *
 * @property int $id
 * @property int $company_id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property WorkspaceStatus $status
 * @property bool $is_default
 */
class Workspace extends Model implements HasMedia
{
    use BelongsToCompany, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'code', 'description', 'status', 'is_default',
        'timezone', 'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkspaceStatus::class,
            'is_default' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsToMany<User, $this, WorkspaceMembership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->using(WorkspaceMembership::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<\App\Modules\Hotel\Models\Hotel, $this>
     */
    public function hotels(): HasMany
    {
        return $this->hasMany(\App\Modules\Hotel\Models\Hotel::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        if ($parts === []) {
            return 'W';
        }

        return strtoupper(collect($parts)->take(2)->map(static fn (string $p): string => mb_substr($p, 0, 1))->implode(''));
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->fit(Fit::Contain, 128, 128);
    }

    protected static function booted(): void
    {
        static::creating(function (self $workspace): void {
            $workspace->uuid ??= (string) Str::ulid();
            $workspace->slug ??= Str::slug($workspace->name) ?: Str::lower(Str::random(8));
        });
    }
}
