<?php

declare(strict_types=1);

use App\Modules\Api\Services\ApiTokenService;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Company\Models\Company;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\User\Models\User;

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function bookingApiHeaders(User $user, Company $company, array $abilities = ['read', 'write']): array
{
    $result = app(ApiTokenService::class)->create($user, $company->id, 'Booking API test', $abilities);

    return ['Authorization' => 'Bearer '.$result['plain_text'], 'Accept' => 'application/json'];
}

function subscribeForBookingApi(): Company
{
    config(['saas.billing.enabled' => true]);

    $company = workspace();

    $plan = Plan::factory()->create([
        'entitlements' => ['api', 'online_booking', 'hotel_management', 'reservations'],
        'limits' => ['seats' => 25, 'hotels' => 3, 'rooms' => 150],
    ]);

    $subscription = new Subscription([
        'plan_id' => $plan->id,
        'gateway' => 'manual',
        'status' => SubscriptionStatus::Active,
        'interval' => BillingInterval::Monthly,
        'quantity' => 1,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'dunning_attempts' => 0,
    ]);
    $subscription->company_id = $company->id;
    $subscription->save();

    return $company;
}

/**
 * @return array{company: Company, hotel: Hotel, roomType: RoomType, room: Room}
 */
function bookableHotelSetup(?Company $company = null): array
{
    $company ??= subscribeForBookingApi();

    $hotel = new Hotel([
        'name' => 'Seaside Resort',
        'currency' => 'USD',
        'status' => HotelStatus::Active,
        'is_active' => true,
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

    return compact('company', 'hotel', 'roomType', 'room');
}

beforeEach(function (): void {
    foreach (['online_booking.book', 'online_booking.manage', 'reservations.view'] as $permission) {
        \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
    }
});

it('lists bookable hotels via the public API', function (): void {
    ['company' => $company, 'hotel' => $hotel] = bookableHotelSetup();
    $user = memberWith(['online_booking.book'], $company);

    $response = $this->withHeaders(bookingApiHeaders($user, $company))
        ->getJson('/api/v1/hotels');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $hotel->uuid)
        ->assertJsonPath('data.0.name', 'Seaside Resort');
});

it('returns availability for a bookable property', function (): void {
    ['company' => $company, 'hotel' => $hotel, 'roomType' => $roomType] = bookableHotelSetup();
    $user = memberWith(['online_booking.book'], $company);

    $response = $this->withHeaders(bookingApiHeaders($user, $company))
        ->getJson('/api/v1/hotels/'.$hotel->uuid.'/availability?check_in_date=2026-09-01&check_out_date=2026-09-03');

    $response->assertOk()
        ->assertJsonPath('data.hotel_id', $hotel->uuid)
        ->assertJsonPath('data.room_types.0.room_type_id', $roomType->id)
        ->assertJsonPath('data.room_types.0.available_rooms', 1);
});

it('creates a booking through the public API', function (): void {
    ['company' => $company, 'hotel' => $hotel, 'roomType' => $roomType] = bookableHotelSetup();
    $user = memberWith(['online_booking.book'], $company);

    $response = $this->withHeaders(bookingApiHeaders($user, $company))
        ->postJson('/api/v1/hotels/'.$hotel->uuid.'/bookings', [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(7)->toDateString(),
            'check_out_date' => now()->addDays(9)->toDateString(),
            'adults' => 2,
            'guest' => [
                'first_name' => 'Jane',
                'last_name' => 'Guest',
                'email' => 'jane@example.com',
            ],
            'external_reference' => 'WEB-1001',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.booking_source', BookingSource::Website->value)
        ->assertJsonPath('data.external_reference', 'WEB-1001')
        ->assertJsonPath('data.guest.email', 'jane@example.com');

    $reservation = Reservation::query()->where('external_reference', 'WEB-1001')->first();
    expect($reservation)->not->toBeNull()
        ->and($reservation->status->value)->toBe('confirmed')
        ->and($reservation->total)->toBe(24000);

    expect(Guest::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('blocks booking API when the plan lacks online booking', function (): void {
    config(['saas.billing.enabled' => true]);
    $company = workspace();
    $plan = Plan::factory()->create(['entitlements' => ['api', 'reservations']]);
    $subscription = new Subscription([
        'plan_id' => $plan->id,
        'gateway' => 'manual',
        'status' => SubscriptionStatus::Active,
        'interval' => BillingInterval::Monthly,
        'quantity' => 1,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'dunning_attempts' => 0,
    ]);
    $subscription->company_id = $company->id;
    $subscription->save();

    $user = memberWith(['online_booking.book'], $company);

    $this->withHeaders(bookingApiHeaders($user, $company))
        ->getJson('/api/v1/hotels')
        ->assertStatus(402);
});

it('does not expose another workspace hotels', function (): void {
    ['hotel' => $hotel] = bookableHotelSetup();
    $otherCompany = subscribeForBookingApi();
    $user = memberWith(['online_booking.book'], $otherCompany);

    $this->withHeaders(bookingApiHeaders($user, $otherCompany))
        ->getJson('/api/v1/hotels/'.$hotel->uuid)
        ->assertNotFound();
});
