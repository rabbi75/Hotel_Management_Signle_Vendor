<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\FloorData;
use App\Modules\Hotel\Models\Floor;

class CreateFloor
{
    public function handle(FloorData $data): Floor
    {
        $floor = new Floor($data->toAttributes());
        $floor->save();

        return $floor;
    }
}