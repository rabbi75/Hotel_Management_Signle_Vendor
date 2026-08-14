<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\Models\Floor;

class DeleteFloor
{
    public function handle(Floor $floor): void
    {
        $floor->delete();
    }
}