<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Modules\Billing\Http\Middleware\EnsureSubscriptionActive;
use App\Modules\Billing\Models\Subscription;
use App\Modules\User\Models\User;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A workspace: the tenant boundary every other record hangs off.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property int $owner_id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $tax_id
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country_code
 * @property string $timezone
 * @property string $currency
 * @property string $locale
 * @property bool $is_active
 * @property CarbonImmutable|null $trial_ends_at
 */
class Company extends Model implements HasMedia
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, InteractsWithMedia, Searchable, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'email',
        'phone',
        'website',
        'tax_id',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'timezone',
        'currency',
        'locale',
        'is_active',
        'trial_ends_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this, CompanyMembership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(CompanyMembership::class)
            ->withPivot(['role', 'job_title', 'department_id', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return HasMany<\App\Modules\Workspace\Models\Workspace, $this>
     */
    public function operationalWorkspaces(): HasMany
    {
        return $this->hasMany(\App\Modules\Workspace\Models\Workspace::class);
    }

    /**
     * @return HasMany<CompanyInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    /**
     * Every subscription this workspace has ever had, newest first.
     *
     * Subscription is tenant-owned, so this relation is only queryable from a
     * context that has already resolved — or deliberately bypassed — the tenant
     * scope. The platform panel does the latter.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest('id');
    }

    /**
     * The subscription that decides what this workspace may do: trialing, active
     * or past due. {@see SubscriptionLimits} resolves the same row for limit
     * checks; this relation exists so a listing can eager-load it instead of
     * asking the service once per workspace.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->live()->latestOfMany();
    }

    // ---------------------------------------------------------------------
    // Behaviour
    // ---------------------------------------------------------------------

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Whether this workspace may use the app under the plan-gating rules.
     *
     * True when a live subscription grants access (trialing, active, past-due in
     * grace) or the workspace is still inside its own signup trial window. The
     * single predicate behind {@see EnsureSubscriptionActive}
     * — a UI badge or a policy can read the same answer from here.
     *
     * Queried without the tenant scope so it is correct from the platform panel,
     * a console command, or any context where no tenant is resolved.
     */
    public function hasActiveAccess(): bool
    {
        if ($this->onTrial()) {
            return true;
        }

        $latest = $this->subscriptions()->withoutGlobalScope(CompanyScope::class)->first();

        return $latest instanceof Subscription && $latest->grantsAccess();
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo', 'thumb') ?: null;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
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

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $company): void {
            $company->uuid ??= (string) Str::ulid();
            $company->slug = $company->slug ?: static::uniqueSlug($company->name);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'immutable_datetime',
        ];
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
