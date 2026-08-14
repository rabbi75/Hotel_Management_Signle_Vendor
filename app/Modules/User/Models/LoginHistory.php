<?php

declare(strict_types=1);

namespace App\Modules\User\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One authentication attempt, successful or not.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property string|null $platform
 * @property string|null $browser
 * @property bool $successful
 * @property string|null $failure_reason
 * @property bool $two_factor_used
 * @property string|null $session_id
 * @property CarbonImmutable $logged_in_at
 * @property CarbonImmutable|null $logged_out_at
 */
class LoginHistory extends Model
{
    protected $fillable = [
        'user_id', 'email', 'ip_address', 'user_agent', 'device_type', 'platform',
        'browser', 'location', 'successful', 'failure_reason', 'two_factor_used',
        'session_id', 'logged_in_at', 'logged_out_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('successful', false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'two_factor_used' => 'boolean',
            'logged_in_at' => 'immutable_datetime',
            'logged_out_at' => 'immutable_datetime',
        ];
    }
}
