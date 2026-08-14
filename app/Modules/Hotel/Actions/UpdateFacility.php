<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\FacilityData;
use App\Modules\Hotel\Models\Facility;

class UpdateFacility
{
    public function handle(Facility $facility, FacilityData $data): Facility
    {
        $facility->fill($data->toUpdateAttributes())->save();

        return $facility;
    }
}