<?php

declare(strict_types=1);

namespace App\Modules\Support\Models;

use App\Modules\Platform\Models\Admin;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $user_id
 * @property int|null $assigned_admin_id
 * @property string $number
 * @property string $subject
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property TicketCategory $category
 * @property CarbonImmutable|null $last_replied_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $closed_at
 */
class SupportTicket extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'assigned_admin_id',
        'number',
        'subject',
        'status',
        'priority',
        'category',
        'last_replied_at',
        'resolved_at',
        'closed_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    /**
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('id');
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            $ticket->uuid ??= (string) Str::ulid();
            $ticket->number ??= 'TMP-'.Str::lower((string) Str::ulid());
        });

        static::created(function (SupportTicket $ticket): void {
            if (str_starts_with($ticket->number, 'TMP-')) {
                $ticket->forceFill(['number' => 'T-'.(10000 + $ticket->id)])->saveQuietly();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'category' => TicketCategory::class,
            'last_replied_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
