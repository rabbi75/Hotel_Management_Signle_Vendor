<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A pending invitation for someone to join a workspace.
 *
 * The token is the only credential required to accept, so it is long, random,
 * unique, and always compared in full rather than by prefix.
 *
 * @property int $id
 * @property int $company_id
 * @property int $invited_by
 * @property string $email
 * @property CompanyRole $role
 * @property InvitationStatus $status
 * @property string $token
 * @property list<string>|null $permission_roles
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 */
class CompanyInvitation extends Model
{
    /** @use HasFactory<CompanyInvitationFactory> */
    use HasFactory;

    protected $fillable = ['company_id', 'invited_by', 'email', 'role', 'status', 'token', 'permission_roles', 'expires_at'];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', InvitationStatus::Pending)->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAcceptable(): bool
    {
        return $this->status === InvitationStatus::Pending && ! $this->isExpired();
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            $invitation->token = $invitation->token ?: Str::random(64);
            $invitation->expires_at ??= now()->addDays((int) config('saas.workspace.invitation_expires_days'));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
            'status' => InvitationStatus::class,
            'permission_roles' => 'array',
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
