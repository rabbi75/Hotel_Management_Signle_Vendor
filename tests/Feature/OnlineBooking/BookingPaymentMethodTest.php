<?php

declare(strict_types=1);

use App\Modules\OnlineBooking\Actions\SeedBookingPaymentMethods;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

it('lets staff manage the seeded payment methods', function (): void {
    $company = workspace();
    app(SeedBookingPaymentMethods::class)->handle($company->id);

    $user = memberWith(['online_booking.manage']);
    actingAsMember($user, $company);

    get(route('booking-payments.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'booking-payments/index')
        ->assertJsonPath('props.methods.0.driver', 'cash_on_delivery');

    $method = BookingPaymentMethod::query()->where('driver', 'bkash')->firstOrFail();

    put(route('booking-payments.update', $method), [
        'name' => 'Hotel bKash',
        'instructions' => 'Send to 01700000000',
        'is_enabled' => true,
        'is_default' => false,
        'sort_order' => 4,
    ])->assertRedirect();

    expect($method->fresh()->name)->toBe('Hotel bKash');
});
