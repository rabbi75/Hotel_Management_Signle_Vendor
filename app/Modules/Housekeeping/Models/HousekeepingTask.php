<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Models;

use App\Modules\Housekeeping\Enums\HousekeepingPriority;
use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Enums\HousekeepingTaskType;
use App\Modules\HotelOperations\Concerns\LogsHotelActivity;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $number
 * @property HousekeepingTaskStatus $status
 */
class HousekeepingTask extends Model
{
    use BelongsToCompany, BelongsToWorkspace, LogsHotelActivity;

    protected $fillable = [
        'hotel_id', 'room_id', 'reservation_id', 'number', 'status', 'priority',
        'task_type', 'assigned_to', 'instructions', 'notes', 'scheduled_for',
        'started_at', 'completed_at', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HousekeepingTaskStatus::class,
            'priority' => HousekeepingPriority::class,
            'task_type' => HousekeepingTaskType::class,
            'scheduled_for' => 'date',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
