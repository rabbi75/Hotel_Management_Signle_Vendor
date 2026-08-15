<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Services\PageRenderer;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\OnlineBooking\Actions\LinkCustomerReservations;
use App\Modules\OnlineBooking\Actions\PlacePublicOrder;
use App\Modules\OnlineBooking\Http\Requests\ConfirmPublicPaymentRequest;
use App\Modules\OnlineBooking\Http\Requests\PlacePublicBookingRequest;
use App\Modules\OnlineBooking\Http\Requests\StorePublicBookingRequest;
use App\Modules\OnlineBooking\Http\Resources\BookingPaymentMethodResource;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\OnlineBooking\Models\Customer;
use App\Modules\OnlineBooking\Services\CheckoutSessionService;
use App\Modules\OnlineBooking\Services\PublicRoomCatalogService;
use App\Modules\OnlineBooking\Services\RateQuoteService;
use App\Modules\OnlineBooking\Services\ResolvePublicBookingProperty;
use App\Modules\Reservation\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicBookingController extends Controller
{
    public function __construct(
        protected ResolvePublicBookingProperty $properties,
        protected PublicRoomCatalogService $catalog,
        protected PageRenderer $pages,
        protected CheckoutSessionService $checkout,
        protected RateQuoteService $rates,
        protected PlacePublicOrder $placeOrder,
        protected LinkCustomerReservations $linkReservations,
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
        $customer = $request->user('customer');

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
            'guest' => $customer instanceof Customer ? [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->getAttribute('phone'),
            ] : null,
            'menus' => $this->menus(),
        ]);
    }

    public function store(StorePublicBookingRequest $request, string $slug): RedirectResponse
    {
        ['hotel' => $hotel] = $this->properties->bySlug($slug);

        $this->checkout->put($request, [
            'slug' => $slug,
            'hotel_id' => $hotel->id,
            'room_type_id' => (int) $request->integer('room_type_id'),
            'check_in_date' => (string) $request->input('check_in_date'),
            'check_out_date' => (string) $request->input('check_out_date'),
            'adults' => (int) $request->integer('adults', 1),
            'children' => (int) $request->integer('children', 0),
            'first_name' => (string) $request->input('first_name'),
            'last_name' => (string) $request->input('last_name'),
            'email' => (string) $request->input('email'),
            'phone' => $request->input('phone') ?: null,
            'special_requests' => $request->input('special_requests') ?: null,
        ]);

        return redirect()->route('booking.checkout', $slug);
    }

    public function checkout(Request $request, string $slug): Response|RedirectResponse
    {
        $cart = $this->checkout->get($request, $slug);

        if ($cart === null) {
            return redirect()->route('booking.show', $slug)
                ->with('warning', __('Select a room and guest details before checkout.'));
        }

        ['hotel' => $hotel] = $this->properties->bySlug($slug);

        $methods = BookingPaymentMethod::query()->enabled()->get();

        return Inertia::render('booking/checkout', [
            'hotel' => $this->hotelPayload($hotel, $slug),
            'cart' => $this->cartPayload($hotel, $cart),
            'methods' => BookingPaymentMethodResource::collection($methods)->resolve($request),
            'customer' => $this->customerPayload($request),
            'menus' => $this->menus(),
        ]);
    }

    public function place(PlacePublicBookingRequest $request, string $slug): RedirectResponse
    {
        $cart = $this->requireCart($request, $slug);
        ['hotel' => $hotel] = $this->properties->bySlug($slug);

        $method = $this->enabledMethod((int) $request->integer('payment_method_id'), $hotel->company_id);
        $customer = $this->resolveCheckoutCustomer($request, $cart);

        if ($method->requiresPrepaid()) {
            $this->checkout->put($request, [...$cart, 'payment_method_id' => $method->id]);

            return redirect()->route('booking.pay', $slug);
        }

        $reservation = $this->placeOrder->handle($cart, $method, $customer);
        $this->checkout->forget($request);

        return redirect()->route('booking.confirmation', [
            'slug' => $slug,
            'number' => $reservation->number,
        ]);
    }

    public function pay(Request $request, string $slug): Response|RedirectResponse
    {
        $cart = $this->checkout->get($request, $slug);

        if ($cart === null || ! isset($cart['payment_method_id'])) {
            return redirect()->route('booking.checkout', $slug);
        }

        ['hotel' => $hotel] = $this->properties->bySlug($slug);
        $method = $this->enabledMethod((int) $cart['payment_method_id'], $hotel->company_id);

        if (! $method->requiresPrepaid()) {
            return redirect()->route('booking.checkout', $slug);
        }

        return Inertia::render('booking/pay', [
            'hotel' => $this->hotelPayload($hotel, $slug),
            'cart' => $this->cartPayload($hotel, $cart),
            'method' => (new BookingPaymentMethodResource($method))->resolve($request),
            'menus' => $this->menus(),
        ]);
    }

    public function confirmPayment(ConfirmPublicPaymentRequest $request, string $slug): RedirectResponse
    {
        $cart = $this->requireCart($request, $slug);

        if (! isset($cart['payment_method_id'])) {
            return redirect()->route('booking.checkout', $slug);
        }

        ['hotel' => $hotel] = $this->properties->bySlug($slug);
        $method = $this->enabledMethod((int) $cart['payment_method_id'], $hotel->company_id);
        $customer = $request->user('customer');

        $reservation = $this->placeOrder->handle(
            $cart,
            $method,
            $customer instanceof Customer ? $customer : null,
            (string) $request->input('payment_reference'),
            prepaid: true,
        );

        $this->checkout->forget($request);

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
            ->with(['guest', 'roomType', 'paymentMethod'])
            ->firstOrFail();

        return Inertia::render('booking/confirmation', [
            'hotel' => $this->hotelPayload($hotel, $slug),
            'reservation' => $this->reservationPayload($reservation, $hotel),
            'menus' => $this->menus(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    protected function cartPayload(Hotel $hotel, array $cart): array
    {
        $roomType = RoomType::query()->whereKey((int) $cart['room_type_id'])->firstOrFail();
        $checkIn = CarbonImmutable::parse((string) $cart['check_in_date'])->startOfDay();
        $checkOut = CarbonImmutable::parse((string) $cart['check_out_date'])->startOfDay();
        $quote = $this->rates->quote($hotel, $roomType, $checkIn, $checkOut);

        return [
            'room_type' => $roomType->name,
            'check_in_date' => $checkIn->toDateString(),
            'check_out_date' => $checkOut->toDateString(),
            'adults' => (int) $cart['adults'],
            'children' => (int) $cart['children'],
            'first_name' => $cart['first_name'],
            'last_name' => $cart['last_name'],
            'email' => $cart['email'],
            'phone' => $cart['phone'] ?? null,
            'special_requests' => $cart['special_requests'] ?? null,
            'nights' => $quote['nights'],
            'nightly_rate' => $quote['nightly_rate'],
            'total' => $quote['total'],
            'currency' => $quote['currency'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reservationPayload(Reservation $reservation, Hotel $hotel): array
    {
        return [
            'number' => $reservation->number,
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'check_in_date' => $reservation->check_in_date->toDateString(),
            'check_out_date' => $reservation->check_out_date->toDateString(),
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'total' => $reservation->total,
            'paid_amount' => $reservation->paid_amount,
            'due_amount' => $reservation->due_amount,
            'currency' => $hotel->currency,
            'room_type' => $reservation->roomType?->name,
            'guest_name' => $reservation->guest?->fullName(),
            'guest_email' => $reservation->guest?->email,
            'payment_method' => $reservation->paymentMethod?->name,
            'payment_status' => $reservation->payment_status?->value,
            'payment_status_label' => $reservation->payment_status?->label(),
            'payment_reference' => $reservation->payment_reference,
        ];
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

    /**
     * @return array<string, mixed>|null
     */
    protected function customerPayload(Request $request): ?array
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            return null;
        }

        return [
            'name' => $customer->fullName(),
            'email' => $customer->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireCart(Request $request, string $slug): array
    {
        $cart = $this->checkout->get($request, $slug);

        if ($cart === null) {
            throw ValidationException::withMessages([
                'room_type_id' => __('Your checkout session expired. Please select the room again.'),
            ]);
        }

        return $cart;
    }

    protected function enabledMethod(int $id, int $companyId): BookingPaymentMethod
    {
        $method = BookingPaymentMethod::query()
            ->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->where('is_enabled', true)
            ->first();

        if (! $method instanceof BookingPaymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => __('Choose a valid payment method.'),
            ]);
        }

        return $method;
    }

    /**
     * @param  array<string, mixed>  $cart
     */
    protected function resolveCheckoutCustomer(Request $request, array $cart): ?Customer
    {
        $existing = $request->user('customer');

        if ($existing instanceof Customer) {
            return $existing;
        }

        if (! $request->boolean('create_account')) {
            return null;
        }

        if (Customer::query()->where('email', $cart['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => __('An account already exists for this email. Sign in to continue.'),
            ]);
        }

        $customer = Customer::query()->create([
            'first_name' => (string) $cart['first_name'],
            'last_name' => (string) $cart['last_name'],
            'email' => (string) $cart['email'],
            'phone' => $cart['phone'] ?? null,
            'password' => (string) $request->input('password'),
        ]);

        Auth::guard('customer')->login($customer);
        $this->linkReservations->handle($customer);

        return $customer;
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
