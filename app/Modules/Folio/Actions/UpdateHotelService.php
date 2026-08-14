<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\DTOs\HotelServiceData;
use App\Modules\Folio\Models\HotelService;

class UpdateHotelService
{
    public function handle(HotelService $service, HotelServiceData $data): HotelService
    {
        $service->fill($data->toUpdateAttributes());
        $service->save();

        return $service;
    }
}
