<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\Models\Building;

class DeleteBuilding
{
    public function handle(Building $building): void
    {
        $building->delete();
    }
}