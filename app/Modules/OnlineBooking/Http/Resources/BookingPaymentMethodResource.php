<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Resources;

use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookingPaymentMethod */
class BookingPaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BookingPaymentMethod $method */
        $method = $this->resource;

        return [
            'id' => $method->id,
            'driver' => $method->driver->value,
            'driver_label' => $method->driver->label(),
            'name' => $method->name,
            'description' => $method->driver->description(),
            'instructions' => $method->instructions,
            'is_enabled' => $method->is_enabled,
            'is_default' => $method->is_default,
            'requires_prepaid' => $method->requiresPrepaid(),
            'sort_order' => $method->sort_order,
        ];
    }
}
