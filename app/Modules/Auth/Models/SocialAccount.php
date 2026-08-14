<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A third-party identity linked to a local account.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string|null $email
 * @property string|null $nickname
 * @property string|null $avatar
 * @property string|null $token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $expires_at
 */
class SocialAccount extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_id', 'email', 'nickname', 'avatar',
        'token', 'refresh_token', 'expires_at',
    ];

    protected $hidden = ['token', 'refresh_token'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
