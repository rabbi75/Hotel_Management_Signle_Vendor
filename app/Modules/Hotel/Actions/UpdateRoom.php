<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\RoomData;
use App\Modules\Hotel\Models\Room;
use Illuminate\Http\UploadedFile;

class UpdateRoom
{
    public function handle(Room $room, RoomData $data, ?UploadedFile $image = null, bool $removeImage = false): Room
    {
        $room->fill($data->toUpdateAttributes())->save();

        if (in_array('facility_ids', $data->provided, true)) {
            $room->facilities()->sync($data->facilityIds);
        }

        if ($removeImage && ! $image instanceof UploadedFile) {
            $room->clearMediaCollection('photo');
        }

        if ($image instanceof UploadedFile) {
            $room->addMedia($image)->toMediaCollection('photo');
        }

        return $room;
    }
}