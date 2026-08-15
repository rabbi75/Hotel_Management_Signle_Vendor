<?php

declare(strict_types=1);

use App\Modules\Company\Models\Company;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\HotelOperations\Notifications\BookingCancelledNotification;
use App\Modules\HotelOperations\Notifications\BookingConfirmedNotification;
use App\Modules\OnlineBooking\Actions\SeedBookingPaymentMethods;
use App\Modules\OnlineBooking\Enums\PaymentMethodDriver;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;

/**
 * @return array{company: Company, hotel: Hotel, roomType: RoomType, room: Room, setting: BookingSetting, reservation: Reservation}
 */
function pendingWebsiteStay(): array
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
        'public_slug' => 'seaside',
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

    $checkIn = now()->addDays(7)->toDateString();
    $checkOut = now()->addDays(9)->toDateString();

    post('/book/seaside', [
        'room_type_id' => $roomType->id,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'adults' => 2,
        'first_name' => 'Jane',
        'last_name' => 'Guest',
        'email' => 'jane@example.com',
    ])->assertRedirect();

    post(route('booking.place', 'seaside'), [
        'payment_method_id' => BookingPaymentMethod::query()
            ->withoutCompanyScope()
            ->where('driver', PaymentMethodDriver::CashOnDelivery)
            ->value('id'),
    ])->assertRedirect();

    $reservation = Reservation::query()->firstOrFail();

    return compact('company', 'hotel', 'roomType', 'room', 'setting', 'reservation');
}

it('confirms a pending website booking and assigns a free room', function (): void {
    Notification::fake();

    ['reservation' => $reservation, 'room' => $room, 'company' => $company] = pendingWebsiteStay();
    $staff = memberWith(['reservations.view', 'reservations.update'], $company)->refresh();

    actingAsMember($staff, $company)
        ->post(route('reservations.confirm', $reservation))
        ->assertRedirect();

    $reservation->refresh();

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->room_id)->toBe($room->id);

    Notification::assertSentOnDemand(BookingConfirmedNotification::class);
});

it('rejects confirming a reservation that is already confirmed', function (): void {
    ['reservation' => $reservation, 'company' => $company] = pendingWebsiteStay();
    $staff = memberWith(['reservations.view', 'reservations.update'], $company)->refresh();

    actingAsMember($staff, $company)
        ->post(route('reservations.confirm', $reservation))
        ->assertRedirect();

    actingAsMember($staff, $company)
        ->from(route('reservations.show', $reservation))
        ->post(route('reservations.confirm', $reservation))
        ->assertSessionHasErrors('status');
});

it('fails confirm when no physical room is free', function (): void {
    ['reservation' => $reservation, 'hotel' => $hotel, 'room' => $room, 'company' => $company] = pendingWebsiteStay();

    $guest = new Guest([
        'hotel_id' => $hotel->id,
        'first_name' => 'Other',
        'last_name' => 'Stay',
        'email' => 'other@example.com',
    ]);
    $guest->company_id = $hotel->company_id;
    $guest->save();

    $blocker = new Reservation([
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_id' => $room->id,
        'room_type_id' => $reservation->room_type_id,
        'check_in_date' => $reservation->check_in_date,
        'check_out_date' => $reservation->check_out_date,
        'adults' => 1,
        'total' => 1000,
        'paid_amount' => 0,
        'due_amount' => 1000,
        'status' => ReservationStatus::Confirmed,
        'booking_source' => BookingSource::WalkIn,
    ]);
    $blocker->company_id = $hotel->company_id;
    $blocker->number = 'RSV-BLOCK';
    $blocker->save();

    $staff = memberWith(['reservations.view', 'reservations.update'], $company)->refresh();

    actingAsMember($staff, $company)
        ->from(route('reservations.show', $reservation))
        ->post(route('reservations.confirm', $reservation))
        ->assertSessionHasErrors('room_id');
});

it('emails the guest when a website booking is cancelled', function (): void {
    Notification::fake();

    ['reservation' => $reservation, 'company' => $company] = pendingWebsiteStay();
    $staff = memberWith(['reservations.view', 'reservations.cancel'], $company)->refresh();

    actingAsMember($staff, $company)
        ->post(route('reservations.cancel', $reservation))
        ->assertRedirect();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);

    Notification::assertSentOnDemand(BookingCancelledNotification::class);
});
