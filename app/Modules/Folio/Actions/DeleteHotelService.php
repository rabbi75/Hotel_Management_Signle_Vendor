<?php

declare(strict_types=1);

namespace App\Modules\Folio\Actions;

use App\Modules\Folio\Models\HotelService;
use Illuminate\Validation\ValidationException;

class DeleteHotelService
{
    public function handle(HotelService $service): void
    {
        if ($service->folioItems()->exists()) {
            throw ValidationException::withMessages([
                'name' => __('This service has been posted to folios and cannot be deleted.'),
            ]);
        }

        $service->delete();
    }
}
