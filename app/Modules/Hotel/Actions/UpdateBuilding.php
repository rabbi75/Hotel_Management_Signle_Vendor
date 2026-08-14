<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\BuildingData;
use App\Modules\Hotel\Models\Building;

class UpdateBuilding
{
    public function handle(Building $building, BuildingData $data): Building
    {
        $building->fill($data->toUpdateAttributes())->save();

        return $building;
    }
}