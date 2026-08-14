<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Modules\Company\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A Sanctum personal access token that is additionally bound to a workspace.
 *
 * Sanctum resolves the token before any tenant is known, so this model
 * deliberately carries no global scope: the workspace is read *off* the token
 * and pushed into the tenant context, never the other way round.
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $token
 * @property list<string>|null $abilities
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 */
class ApiToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * The first characters of the stored hash, shown so a user can tell two
     * tokens apart in the UI. The plaintext is never recoverable.
     */
    public function fingerprint(): string
    {
        return substr($this->token, 0, 8);
    }
}
