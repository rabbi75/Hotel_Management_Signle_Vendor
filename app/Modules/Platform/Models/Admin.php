<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Notifications\AdminPasswordResetNotification;
use Carbon\CarbonImmutable;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * A platform operator.
 *
 * Authenticates on the `admin` guard against the `admins` table — entirely
 * separate from the tenant `User`. Holds spatie roles scoped to the same guard,
 * so a `platform.*` permission on an Admin never collides with a tenant's.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string $status
 * @property string|null $avatar_path
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 */
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /** The guard spatie resolves this model's roles and permissions against. */
    protected string $guard_name = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return HasMany<AdminLoginHistory, $this>
     */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(AdminLoginHistory::class)->latest('logged_in_at');
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
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

    public function avatarUrl(): ?string
    {
        return $this->avatar_path !== null ? asset('storage/'.$this->avatar_path) : null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminPasswordResetNotification($token));
    }

    protected static function booted(): void
    {
        static::creating(function (self $admin): void {
            $admin->uuid ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
