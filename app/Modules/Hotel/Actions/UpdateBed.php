<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\DTOs\BedData;
use App\Modules\Hotel\Models\Bed;

class UpdateBed
{
    public function handle(Bed $bed, BedData $data): Bed
    {
        $bed->fill($data->toUpdateAttributes())->save();

        return $bed;
    }
}