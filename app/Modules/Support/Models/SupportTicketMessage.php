<?php

declare(strict_types=1);

namespace App\Modules\Support\Models;

use App\Modules\Platform\Models\Admin;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $user_id
 * @property int|null $admin_id
 * @property string $body
 * @property bool $is_internal
 * @property CarbonImmutable|null $created_at
 */
class SupportTicketMessage extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'admin_id',
        'body',
        'is_internal',
    ];

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
            'is_internal' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
