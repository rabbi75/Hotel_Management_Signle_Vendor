<?php

declare(strict_types=1);

use App\Modules\OnlineBooking\Models\Customer;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('registers a guest account and opens the dashboard', function (): void {
    workspace();

    post(route('account.register.store'), [
        'first_name' => 'Amina',
        'last_name' => 'Rahman',
        'email' => 'amina@example.com',
        'phone' => '+8801700000000',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ])->assertRedirect(route('account.dashboard'));

    $this->assertAuthenticated('customer');

    get(route('account.dashboard'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'account/dashboard')
        ->assertJsonPath('props.customer.email', 'amina@example.com');
});

it('lists bookings attached to the signed-in guest', function (): void {
    ['hotel' => $hotel, 'roomType' => $roomType] = publicBookableHotel();
    $companyId = $hotel->company_id;

    $customer = Customer::query()->create([
        'first_name' => 'Amina',
        'last_name' => 'Rahman',
        'email' => 'amina@example.com',
        'phone' => null,
        'password' => 'password1',
    ]);

    $guest = \App\Modules\Guest\Models\Guest::query()->create([
        'hotel_id' => $hotel->id,
        'first_name' => 'Amina',
        'last_name' => 'Rahman',
        'email' => 'amina@example.com',
    ]);

    $reservation = new Reservation([
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'customer_id' => $customer->id,
        'room_type_id' => $roomType->id,
        'number' => 'RSV-TEST-0001',
        'check_in_date' => now()->addDays(3)->toDateString(),
        'check_out_date' => now()->addDays(5)->toDateString(),
        'adults' => 1,
        'total' => 24000,
        'paid_amount' => 0,
        'due_amount' => 24000,
        'booking_source' => BookingSource::Website,
        'status' => ReservationStatus::Pending,
    ]);
    $reservation->company_id = $companyId;
    $reservation->save();

    actingAs($customer, 'customer');

    get(route('account.bookings'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.bookings.0.number', 'RSV-TEST-0001');
});
