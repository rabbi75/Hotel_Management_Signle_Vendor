<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Services\RoomStatusSync;
use App\Modules\Maintenance\DTOs\MaintenanceRequestData;
use App\Modules\Maintenance\Enums\MaintenanceCategory;
use App\Modules\Maintenance\Enums\MaintenancePriority;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\HotelOperations\Events\MaintenanceRequestCreated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateMaintenanceRequest
{
    public function __construct(protected RoomStatusSync $roomStatus) {}

    public function handle(MaintenanceRequestData $data): MaintenanceRequest
    {
        $request = DB::transaction(function () use ($data): MaintenanceRequest {
            if ($data->roomId !== null) {
                $room = Room::query()->where('hotel_id', $data->hotelId)->whereKey($data->roomId)->first();

                if (! $room instanceof Room) {
                    throw ValidationException::withMessages([
                        'room_id' => __('The selected room does not belong to this property.'),
                    ]);
                }
            }

            if ($data->bedId !== null) {
                $bed = Bed::query()->where('hotel_id', $data->hotelId)->whereKey($data->bedId)->first();

                if (! $bed instanceof Bed) {
                    throw ValidationException::withMessages([
                        'bed_id' => __('The selected bed does not belong to this property.'),
                    ]);
                }
            }

            $request = new MaintenanceRequest([
                ...$data->toAttributes(),
                'number' => static::nextNumber(),
                'status' => MaintenanceRequestStatus::Open,
                'category' => MaintenanceCategory::tryFrom($data->category) ?? MaintenanceCategory::Other,
                'priority' => MaintenancePriority::tryFrom($data->priority) ?? MaintenancePriority::Normal,
                'reported_by' => auth()->id(),
                'due_at' => $data->dueAt !== null ? CarbonImmutable::parse($data->dueAt) : null,
            ]);
            $request->save();

            $request->loadMissing(['room', 'bed']);

            if ($request->room !== null) {
                $this->roomStatus->applyMaintenanceBlock($request->room, $request->blocks_room);
            }

            $this->roomStatus->applyBedMaintenanceBlock($request->bed, $request->blocks_room);

            return $request->fresh(['room', 'bed', 'hotel', 'assignee', 'reporter']) ?? $request;
        });

        MaintenanceRequestCreated::dispatch($request);

        return $request;
    }

    public static function nextNumber(): string
    {
        $prefix = 'MNT-'.now()->format('Ymd').'-';

        do {
            $number = $prefix.Str::upper(Str::random(4));
        } while (MaintenanceRequest::query()->where('number', $number)->exists());

        return $number;
    }
}
