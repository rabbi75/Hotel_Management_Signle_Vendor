<?php

declare(strict_types=1);

namespace App\Modules\User\Models;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyMembership;
use App\Modules\Company\Models\Team;
use App\Modules\Workspace\Models\Workspace;
use App\Modules\Workspace\Models\WorkspaceMembership;
use App\Modules\User\Enums\UserStatus;
use App\Support\Enums\Theme;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property string|null $bio
 * @property UserStatus $status
 * @property int|null $current_company_id
 * @property int|null $current_workspace_id
 * @property string $timezone
 * @property string $locale
 * @property Theme $theme
 * @property array<string, mixed>|null $preferences
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 * @property CarbonImmutable|null $suspended_at
 * @property string|null $suspended_reason
 */
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable, Searchable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'bio',
        'password',
        'status',
        'timezone',
        'locale',
        'theme',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * @return BelongsToMany<Company, $this, CompanyMembership>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(CompanyMembership::class)
            ->withPivot(['role', 'job_title', 'department_id', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * @return BelongsToMany<Workspace, $this, WorkspaceMembership>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->using(WorkspaceMembership::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /**
     * @return HasMany<LoginHistory, $this>
     */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class)->latest('logged_in_at');
    }

    // ---------------------------------------------------------------------
    // State
    // ---------------------------------------------------------------------

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('permissions.super_admin_role', 'super-admin'));
    }

    /**
     * Whether this user belongs to the given workspace.
     */
    public function belongsToCompany(Company|int $company): bool
    {
        $id = $company instanceof Company ? $company->id : $company;

        return $this->companies()->whereKey($id)->exists();
    }

    /**
     * The user's role inside a workspace, or null when they are not a member.
     */
    public function membershipRole(Company|int $company): ?CompanyRole
    {
        $id = $company instanceof Company ? $company->id : $company;

        /** @var Company|null $found */
        $found = $this->companies()->whereKey($id)->first();

        /** @var CompanyMembership|null $membership */
        $membership = $found?->getRelationValue('pivot');

        return $membership?->role;
    }

    // ---------------------------------------------------------------------
    // Presentation
    // ---------------------------------------------------------------------

    public function initials(): string
    {
        $source = trim("{$this->first_name} {$this->last_name}") ?: $this->name;

        return Str::of($source)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function avatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // nonQueued() before fit(): the avatar has to exist the instant the
        // upload response returns, or the UI shows a broken image.
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->fit(Fit::Crop, 128, 128);

        $this->addMediaConversion('preview')
            ->fit(Fit::Crop, 400, 400);
    }

    // ---------------------------------------------------------------------
    // Permissions
    // ---------------------------------------------------------------------

    /**
     * Flat list of permission names, cached for the request lifetime and in
     * Redis. Shared with the frontend so navigation and action buttons can be
     * gated without a round trip per check.
     *
     * @return list<string>
     */
    public function cachedPermissionNames(): array
    {
        return Cache::remember(
            "user:{$this->id}:permissions",
            (int) config('saas.cache.permissions_ttl'),
            fn (): array => $this->getAllPermissions()->pluck('name')->values()->all(),
        );
    }

    public function flushPermissionCache(): void
    {
        Cache::forget("user:{$this->id}:permissions");
    }

    // ---------------------------------------------------------------------
    // Search
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status !== UserStatus::Suspended;
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->uuid ??= (string) Str::ulid();
            $user->name = $user->name ?: trim("{$user->first_name} {$user->last_name}");
        });

        static::saved(fn (self $user) => $user->flushPermissionCache());
        static::deleted(fn (self $user) => $user->flushPermissionCache());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password_changed_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'status' => UserStatus::class,
            'theme' => Theme::class,
        ];
    }
}
