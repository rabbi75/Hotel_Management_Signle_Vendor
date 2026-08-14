<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded sign-in (or attempt) against the admin console.
 *
 * @property int $id
 * @property int $admin_id
 * @property string|null $ip_address
 * @property string|null $platform
 * @property string|null $browser
 * @property bool $successful
 * @property CarbonImmutable $logged_in_at
 */
class AdminLoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'ip_address',
        'platform',
        'browser',
        'successful',
        'logged_in_at',
    ];

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'logged_in_at' => 'immutable_datetime',
        ];
    }
}
