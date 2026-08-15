<?php

declare(strict_types=1);

use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\HotelOperations\Notifications\BookingRequestReceivedNotification;
use App\Modules\OnlineBooking\Actions\SeedBookingPaymentMethods;
use App\Modules\OnlineBooking\Enums\PaymentMethodDriver;
use App\Modules\OnlineBooking\Enums\PaymentStatus;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{hotel: Hotel, roomType: RoomType, room: Room, setting: BookingSetting}
 */
function publicBookableHotel(string $slug = 'seaside'): array
{
    $company = workspace();

    $hotel = new Hotel([
        'name' => 'Seaside Resort',
        'currency' => 'USD',
        'status' => HotelStatus::Active,
        'is_active' => true,
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
    ]);
    $hotel->company_id = $company->id;
    $hotel->save();

    $setting = new BookingSetting([
        'hotel_id' => $hotel->id,
        'is_enabled' => true,
        'public_slug' => $slug,
        'min_advance_days' => 0,
        'max_advance_days' => 365,
    ]);
    $setting->company_id = $company->id;
    $setting->save();

    app(SeedBookingPaymentMethods::class)->handle($company->id);

    $roomType = new RoomType([
        'hotel_id' => $hotel->id,
        'name' => 'Standard Double',
        'code' => 'STD',
        'base_price' => 12000,
        'max_adults' => 2,
        'max_occupancy' => 2,
        'is_active' => true,
    ]);
    $roomType->company_id = $company->id;
    $roomType->save();

    $room = new Room([
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'status' => RoomStatus::Available,
        'is_active' => true,
    ]);
    $room->company_id = $company->id;
    $room->save();

    return compact('hotel', 'roomType', 'room', 'setting');
}

function publicPaymentMethodId(string $driver = 'cash_on_delivery'): int
{
    return (int) BookingPaymentMethod::query()
        ->withoutCompanyScope()
        ->where('driver', $driver)
        ->value('id');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function publicStayPayload(int $roomTypeId, array $overrides = []): array
{
    return [
        'room_type_id' => $roomTypeId,
        'check_in_date' => now()->addDays(7)->toDateString(),
        'check_out_date' => now()->addDays(9)->toDateString(),
        'adults' => 2,
        'children' => 0,
        'first_name' => 'Jane',
        'last_name' => 'Guest',
        'email' => 'jane@example.com',
        'phone' => '+1 415 555 0100',
        ...$overrides,
    ];
}

it('redirects /book to the enabled property slug', function (): void {
    publicBookableHotel('seaside');

    get('/book')->assertRedirect(route('booking.show', 'seaside'));
});

it('shows availability for the requested dates', function (): void {
    ['roomType' => $roomType] = publicBookableHotel();

    get('/book/seaside?check_in_date='.now()->addDays(3)->toDateString().'&check_out_date='.now()->addDays(5)->toDateString(), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'booking/show')
        ->assertJsonPath('props.availability.0.room_type_id', $roomType->id)
        ->assertJsonPath('props.availability.0.available_rooms', 1);
});

it('loads tonight availability without a date search', function (): void {
    ['roomType' => $roomType] = publicBookableHotel();

    get('/book/seaside', inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'booking/show')
        ->assertJsonPath('props.filters.check_in_date', now()->toDateString())
        ->assertJsonPath('props.filters.check_out_date', now()->addDay()->toDateString())
        ->assertJsonPath('props.rooms.0.room_type_id', $roomType->id)
        ->assertJsonPath('props.rooms.0.available_rooms', 1);
});

it('preselects a room type from the query string', function (): void {
    ['roomType' => $roomType] = publicBookableHotel();

    get('/book/seaside?room_type_id='.$roomType->id, inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.filters.room_type_id', $roomType->id);
});

it('sends the guest to checkout without creating a reservation', function (): void {
    ['roomType' => $roomType] = publicBookableHotel();

    post('/book/seaside', publicStayPayload($roomType->id))
        ->assertRedirect(route('booking.checkout', 'seaside'));

    expect(Reservation::query()->count())->toBe(0);

    get(route('booking.checkout', 'seaside'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'booking/checkout')
        ->assertJsonPath('props.cart.email', 'jane@example.com');
});

it('places a pending cash-on-delivery order from checkout', function (): void {
    Notification::fake();

    ['roomType' => $roomType] = publicBookableHotel();

    post('/book/seaside', publicStayPayload($roomType->id))->assertRedirect();

    $response = post(route('booking.place', 'seaside'), [
        'payment_method_id' => publicPaymentMethodId(PaymentMethodDriver::CashOnDelivery->value),
    ]);

    $reservation = Reservation::query()->where('guest_id', Guest::query()->where('email', 'jane@example.com')->value('id'))->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->booking_source)->toBe(BookingSource::Website)
        ->and($reservation->total)->toBe(24000)
        ->and($reservation->paid_amount)->toBe(0)
        ->and($reservation->payment_status)->toBe(PaymentStatus::Unpaid);

    $response->assertRedirect(route('booking.confirmation', [
        'slug' => 'seaside',
        'number' => $reservation->number,
    ]));

    Notification::assertSentOnDemand(BookingRequestReceivedNotification::class);

    get(route('booking.confirmation', ['slug' => 'seaside', 'number' => $reservation->number]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.reservation.status', 'pending')
        ->assertJsonPath('props.reservation.payment_status', 'unpaid');
});

it('places a prepaid order only after payment is confirmed', function (): void {
    Notification::fake();

    ['roomType' => $roomType] = publicBookableHotel();

    post('/book/seaside', publicStayPayload($roomType->id))->assertRedirect();

    post(route('booking.place', 'seaside'), [
        'payment_method_id' => publicPaymentMethodId(PaymentMethodDriver::Bkash->value),
    ])->assertRedirect(route('booking.pay', 'seaside'));

    expect(Reservation::query()->count())->toBe(0);

    $response = post(route('booking.pay.store', 'seaside'), [
        'payment_reference' => 'BKH-998877',
    ]);

    $reservation = Reservation::query()->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->payment_status)->toBe(PaymentStatus::Paid)
        ->and($reservation->paid_amount)->toBe(24000)
        ->and($reservation->payment_reference)->toBe('BKH-998877');

    $response->assertRedirect(route('booking.confirmation', [
        'slug' => 'seaside',
        'number' => $reservation->number,
    ]));

    Notification::assertSentOnDemand(BookingRequestReceivedNotification::class);
});

it('rejects a booking when the room type is sold out', function (): void {
    ['hotel' => $hotel, 'roomType' => $roomType] = publicBookableHotel();

    $checkIn = now()->addDays(4)->toDateString();
    $checkOut = now()->addDays(6)->toDateString();

    post('/book/seaside', publicStayPayload($roomType->id, [
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'adults' => 1,
        'first_name' => 'First',
        'email' => 'first@example.com',
    ]))->assertRedirect();

    post(route('booking.place', 'seaside'), [
        'payment_method_id' => publicPaymentMethodId(),
    ])->assertRedirect();

    post('/book/seaside', publicStayPayload($roomType->id, [
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'adults' => 1,
        'first_name' => 'Second',
        'email' => 'second@example.com',
    ]))->assertRedirect();

    post(route('booking.place', 'seaside'), [
        'payment_method_id' => publicPaymentMethodId(),
    ])->assertSessionHasErrors('room_type_id');

    expect(Reservation::query()->where('hotel_id', $hotel->id)->count())->toBe(1);
});

it('returns 404 when online booking is disabled', function (): void {
    ['setting' => $setting] = publicBookableHotel('closed-inn');
    $setting->forceFill(['is_enabled' => false])->save();

    get('/book/closed-inn')->assertNotFound();
});

it('returns 404 from /book when no property is bookable', function (): void {
    workspace();

    get('/book')->assertNotFound();
});
