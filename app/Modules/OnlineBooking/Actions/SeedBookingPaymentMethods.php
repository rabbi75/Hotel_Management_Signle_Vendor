<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Actions;

use App\Modules\OnlineBooking\Enums\PaymentMethodDriver;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;

class SeedBookingPaymentMethods
{
    public function handle(int $companyId): void
    {
        foreach (PaymentMethodDriver::cases() as $index => $driver) {
            $method = BookingPaymentMethod::query()
                ->withoutCompanyScope()
                ->firstOrNew([
                    'company_id' => $companyId,
                    'driver' => $driver->value,
                ]);

            $method->forceFill([
                'company_id' => $companyId,
                'name' => $method->exists ? $method->name : $driver->label(),
                'instructions' => $method->exists ? $method->instructions : $driver->defaultInstructions(),
                'is_enabled' => $method->exists ? $method->is_enabled : true,
                'is_default' => $method->exists
                    ? $method->is_default
                    : $driver === PaymentMethodDriver::CashOnDelivery,
                'sort_order' => $method->exists ? $method->sort_order : $index,
            ]);
            $method->save();
        }
    }
}
