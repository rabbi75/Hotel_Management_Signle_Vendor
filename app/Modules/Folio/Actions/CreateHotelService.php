<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\DTOs\HotelServiceData;
use App\Modules\Folio\Models\HotelService;

class CreateHotelService
{
    public function handle(HotelServiceData $data): HotelService
    {
        $service = new HotelService($data->toAttributes());
        $service->save();

        return $service;
    }
}
