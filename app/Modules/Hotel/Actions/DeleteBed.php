<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Hotel\Models\Bed;

class DeleteBed
{
    public function handle(Bed $bed): void
    {
        $bed->delete();
    }
}