<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Services;

use App\Modules\Hotel\Enums\BedStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Room;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;

class RoomStatusSync
{
    public function markCleaning(Room $room): void
    {
        if (in_array($room->status, [RoomStatus::Dirty, RoomStatus::Available], true)) {
            $room->update(['status' => RoomStatus::Cleaning]);
        }
    }

    public function markAvailableAfterHousekeeping(Room $room): void
    {
        if ($this->hasBlockingMaintenance($room)) {
            return;
        }

        if (in_array($room->status, [RoomStatus::Dirty, RoomStatus::Cleaning], true)) {
            $room->update(['status' => RoomStatus::Available]);
        }
    }

    public function applyMaintenanceBlock(Room $room, bool $blocksRoom): void
    {
        if (! $blocksRoom) {
            return;
        }

        $room->update(['status' => RoomStatus::Maintenance]);
    }

    public function releaseMaintenanceBlock(?Room $room): void
    {
        if (! $room instanceof Room) {
            return;
        }

        if ($this->hasBlockingMaintenance($room)) {
            return;
        }

        if ($room->status === RoomStatus::Maintenance) {
            $room->update(['status' => RoomStatus::Available]);
        }
    }

    public function applyBedMaintenanceBlock(?Bed $bed, bool $blocksRoom): void
    {
        if (! $bed instanceof Bed || ! $blocksRoom) {
            return;
        }

        $bed->update(['status' => BedStatus::Maintenance]);
    }

    public function releaseBedMaintenanceBlock(?Bed $bed): void
    {
        if (! $bed instanceof Bed) {
            return;
        }

        if ($bed->status === BedStatus::Maintenance) {
            $bed->update(['status' => BedStatus::Available]);
        }
    }

    protected function hasBlockingMaintenance(Room $room): bool
    {
        return MaintenanceRequest::query()
            ->where('room_id', $room->id)
            ->where('blocks_room', true)
            ->whereIn('status', [
                MaintenanceRequestStatus::Open->value,
                MaintenanceRequestStatus::InProgress->value,
            ])
            ->exists();
    }
}
