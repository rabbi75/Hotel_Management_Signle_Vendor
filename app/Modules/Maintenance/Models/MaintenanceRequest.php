<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Maintenance\Enums\MaintenanceCategory;
use App\Modules\Maintenance\Enums\MaintenancePriority;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\HotelOperations\Concerns\LogsHotelActivity;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $number
 * @property MaintenanceRequestStatus $status
 * @property bool $blocks_room
 */
class MaintenanceRequest extends Model
{
    use BelongsToCompany, BelongsToWorkspace, LogsHotelActivity;

    protected $fillable = [
        'hotel_id', 'room_id', 'bed_id', 'number', 'title', 'description',
        'category', 'priority', 'status', 'blocks_room', 'reported_by',
        'assigned_to', 'due_at', 'started_at', 'completed_at', 'resolution_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceRequestStatus::class,
            'blocks_room' => 'boolean',
            'due_at' => 'immutable_datetime',
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
     * @return BelongsTo<Bed, $this>
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function dueAt(): ?CarbonImmutable
    {
        return $this->due_at;
    }
}
