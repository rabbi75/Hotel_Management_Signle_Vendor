<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\FacilityData;
use App\Modules\Hotel\Models\Facility;

class CreateFacility
{
    public function handle(FacilityData $data): Facility
    {
        $facility = new Facility($data->toAttributes());
        $facility->save();

        return $facility;
    }
}