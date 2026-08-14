<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\BuildingData;
use App\Modules\Hotel\Models\Building;

class CreateBuilding
{
    public function handle(BuildingData $data): Building
    {
        $building = new Building($data->toAttributes());
        $building->save();

        return $building;
    }
}