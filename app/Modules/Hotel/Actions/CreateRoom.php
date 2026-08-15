<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Hotel\DTOs\RoomData;
use App\Modules\Hotel\Models\Room;
use Illuminate\Http\UploadedFile;

class CreateRoom
{
    public function __construct(protected SubscriptionLimits $limits) {}

    public function handle(RoomData $data, ?UploadedFile $image = null): Room
    {
        $this->limits->ensure('rooms');

        $room = new Room($data->toAttributes());
        $room->save();

        if ($data->facilityIds !== []) {
            $room->facilities()->sync($data->facilityIds);
        }

        if ($image instanceof UploadedFile) {
            $room->addMedia($image)->toMediaCollection('photo');
        }

        return $room;
    }
}