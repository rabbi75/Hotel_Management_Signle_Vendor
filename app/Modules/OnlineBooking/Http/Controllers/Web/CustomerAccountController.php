<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CMS\Enums\MenuLocation;
use App\Modules\CMS\Services\PageRenderer;
use App\Modules\OnlineBooking\Http\Requests\UpdateCustomerProfileRequest;
use App\Modules\OnlineBooking\Models\Customer;
use App\Modules\OnlineBooking\Services\ResolvePublicBookingProperty;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccountController extends Controller
{
    public function __construct(
        protected PageRenderer $pages,
        protected ResolvePublicBookingProperty $properties,
    ) {}

    public function dashboard(Request $request): Response
    {
        $customer = $this->customer($request);
        $bookings = $this->bookingsQuery($customer)->limit(5)->get();

        return Inertia::render('account/dashboard', [
            'customer' => $this->customerPayload($customer),
            'stats' => [
                'bookings' => $this->bookingsQuery($customer)->count(),
                'upcoming' => $this->bookingsQuery($customer)
                    ->whereDate('check_in_date', '>=', now()->toDateString())
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->count(),
            ],
            'recent' => $bookings->map(fn (Reservation $reservation): array => $this->bookingRow($reservation))->all(),
            'menus' => $this->menus(),
        ]);
    }

    public function bookings(Request $request): Response
    {
        $customer = $this->customer($request);

        $bookings = $this->bookingsQuery($customer)
            ->get()
            ->map(fn (Reservation $reservation): array => $this->bookingRow($reservation))
            ->all();

        return Inertia::render('account/bookings', [
            'customer' => $this->customerPayload($customer),
            'bookings' => $bookings,
            'menus' => $this->menus(),
        ]);
    }

    public function show(Request $request, string $number): Response
    {
        $customer = $this->customer($request);

        $reservation = $this->bookingsQuery($customer)
            ->where('number', $number)
            ->firstOrFail();

        $setting = $reservation->hotel?->bookingSetting;

        return Inertia::render('account/booking-show', [
            'customer' => $this->customerPayload($customer),
            'booking' => $this->bookingRow($reservation),
            'confirmation_url' => $setting?->public_slug
                ? route('booking.confirmation', ['slug' => $setting->public_slug, 'number' => $reservation->number])
                : null,
            'menus' => $this->menus(),
        ]);
    }

    public function profile(Request $request): Response
    {
        $customer = $this->customer($request);

        return Inertia::render('account/profile', [
            'customer' => $this->customerPayload($customer),
            'menus' => $this->menus(),
        ]);
    }

    public function updateProfile(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $data = $request->safe()->only(['first_name', 'last_name', 'email', 'phone']);

        if (filled($request->input('password'))) {
            $data['password'] = $request->input('password');
        }

        $customer->fill($data)->save();

        return back()->with('success', __('Profile updated.'));
    }

    protected function customer(Request $request): Customer
    {
        $customer = $request->user('customer');

        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Reservation>
     */
    protected function bookingsQuery(Customer $customer)
    {
        return Reservation::query()
            ->withoutCompanyScope()
            ->withoutWorkspaceScope()
            ->with(['hotel', 'roomType', 'paymentMethod'])
            ->where('customer_id', $customer->id)
            ->latest();
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerPayload(Customer $customer): array
    {
        return [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'name' => $customer->fullName(),
            'email' => $customer->email,
            'phone' => $customer->getAttribute('phone'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingRow(Reservation $reservation): array
    {
        return [
            'number' => $reservation->number,
            'hotel' => $reservation->hotel?->name,
            'room_type' => $reservation->roomType?->name,
            'check_in_date' => $reservation->check_in_date->toDateString(),
            'check_out_date' => $reservation->check_out_date->toDateString(),
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'total' => $reservation->total,
            'paid_amount' => $reservation->paid_amount,
            'due_amount' => $reservation->due_amount,
            'currency' => $reservation->hotel?->currency ?? 'USD',
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'payment_method' => $reservation->paymentMethod?->name,
            'payment_status' => $reservation->payment_status?->value,
            'payment_status_label' => $reservation->payment_status?->label(),
            'payment_reference' => $reservation->payment_reference,
            'special_requests' => $reservation->special_requests,
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
}
