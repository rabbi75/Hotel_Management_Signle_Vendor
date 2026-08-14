<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\BedData;
use App\Modules\Hotel\Models\Bed;

class CreateBed
{
    public function handle(BedData $data): Bed
    {
        $bed = new Bed($data->toAttributes());
        $bed->save();

        return $bed;
    }
}