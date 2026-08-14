<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Services\PageRenderer;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\HotelOperations\Events\WebsiteBookingRequested;
use App\Modules\OnlineBooking\Actions\CreateOnlineBooking;
use App\Modules\OnlineBooking\DTOs\OnlineBookingData;
use App\Modules\OnlineBooking\Http\Requests\StorePublicBookingRequest;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\OnlineBooking\Services\PublicRoomCatalogService;
use App\Modules\OnlineBooking\Services\ResolvePublicBookingProperty;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicBookingController extends Controller
{
    public function __construct(
        protected ResolvePublicBookingProperty $properties,
        protected PublicRoomCatalogService $catalog,
        protected CreateOnlineBooking $createBooking,
        protected PageRenderer $pages,
    ) {}

    public function index(): RedirectResponse
    {
        $setting = $this->properties->firstEnabled();

        abort_unless($setting instanceof BookingSetting, 404);

        return redirect()->route('booking.show', $setting->public_slug);
    }

    public function show(Request $request, string $slug): Response
    {
        $catalog = $this->catalog->forPublicProperty(
            $slug,
            $this->dateOrNull($request->query('check_in_date')),
            $this->dateOrNull($request->query('check_out_date')),
        );

        abort_unless($catalog !== null, 404);

        $rooms = $catalog['rooms'];

        return Inertia::render('booking/show', [
            'hotel' => $this->hotelPayload($catalog['hotel'], $slug),
            'rooms' => $rooms,
            'availability' => array_map(static fn (array $row): array => [
                'room_type_id' => $row['room_type_id'],
                'room_type' => $row['name'],
                'code' => $row['code'],
                'available_rooms' => $row['available_rooms'],
                'nightly_rate' => $row['nightly_rate'],
                'nights' => $row['nights'],
                'subtotal' => $row['subtotal'],
                'currency' => $row['currency'],
            ], $rooms),
            'filters' => [
                'check_in_date' => $catalog['check_in']->toDateString(),
                'check_out_date' => $catalog['check_out']->toDateString(),
                'adults' => max(1, (int) $request->query('adults', 1)),
                'children' => max(0, (int) $request->query('children', 0)),
                'room_type_id' => $request->query('room_type_id') ? (int) $request->query('room_type_id') : null,
            ],
            'menus' => $this->menus(),
        ]);
    }

    public function store(StorePublicBookingRequest $request, string $slug): RedirectResponse
    {
        ['hotel' => $hotel] = $this->properties->bySlug($slug);

        $reservation = $this->createBooking->handle(new OnlineBookingData(
            hotelId: $hotel->id,
            roomTypeId: (int) $request->integer('room_type_id'),
            checkInDate: (string) $request->input('check_in_date'),
            checkOutDate: (string) $request->input('check_out_date'),
            guestFirstName: (string) $request->input('first_name'),
            guestLastName: (string) $request->input('last_name'),
            guestEmail: (string) $request->input('email'),
            guestPhone: $request->input('phone') ?: null,
            adults: (int) $request->integer('adults', 1),
            children: (int) $request->integer('children', 0),
            specialRequests: $request->input('special_requests') ?: null,
            bookingSource: BookingSource::Website,
            status: ReservationStatus::Pending,
        ));

        WebsiteBookingRequested::dispatch($reservation);

        return redirect()->route('booking.confirmation', [
            'slug' => $slug,
            'number' => $reservation->number,
        ]);
    }

    public function confirmation(string $slug, string $number): Response
    {
        ['hotel' => $hotel] = $this->properties->bySlug($slug);

        $reservation = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('number', $number)
            ->with(['guest', 'roomType'])
            ->firstOrFail();

        return Inertia::render('booking/confirmation', [
            'hotel' => $this->hotelPayload($hotel, $slug),
            'reservation' => [
                'number' => $reservation->number,
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
                'check_in_date' => $reservation->check_in_date->toDateString(),
                'check_out_date' => $reservation->check_out_date->toDateString(),
                'adults' => $reservation->adults,
                'children' => $reservation->children,
                'total' => $reservation->total,
                'currency' => $hotel->currency,
                'room_type' => $reservation->roomType?->name,
                'guest_name' => $reservation->guest?->fullName(),
                'guest_email' => $reservation->guest?->email,
            ],
            'menus' => $this->menus(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function hotelPayload(Hotel $hotel, string $slug): array
    {
        return [
            'name' => $hotel->name,
            'slug' => $slug,
            'description' => $hotel->description,
            'city' => $hotel->city,
            'country' => $hotel->country,
            'currency' => $hotel->currency,
            'check_in_time' => $hotel->check_in_time,
            'check_out_time' => $hotel->check_out_time,
            'phone' => $hotel->phone,
            'email' => $hotel->email,
            'cover' => $hotel->coverUrl(),
        ];
    }

    /**
     * @return array{header: list<array<string, mixed>>, footer: list<array<string, mixed>>}
     */
    protected function menus(): array
    {
        return [
            'header' => $this->pages->menu(MenuLocation::Header),
            'footer' => $this->pages->menu(MenuLocation::Footer),
        ];
    }

    protected function dateOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
