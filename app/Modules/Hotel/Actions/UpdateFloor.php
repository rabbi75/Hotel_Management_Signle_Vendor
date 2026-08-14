<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\FloorData;
use App\Modules\Hotel\Models\Floor;

class UpdateFloor
{
    public function handle(Floor $floor, FloorData $data): Floor
    {
        $floor->fill($data->toUpdateAttributes())->save();

        return $floor;
    }
}