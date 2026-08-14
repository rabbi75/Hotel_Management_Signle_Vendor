<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\Models\Facility;

class DeleteFacility
{
    public function handle(Facility $facility): void
    {
        $facility->hotels()->detach();
        $facility->roomTypes()->detach();
        $facility->rooms()->detach();
        $facility->delete();
    }
}