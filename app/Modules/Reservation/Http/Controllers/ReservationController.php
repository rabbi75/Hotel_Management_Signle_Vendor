<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Folio\Http\Resources\GuestFolioResource;
use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\Reservation\Actions\CancelReservation;
use App\Modules\Reservation\Actions\CheckInReservation;
use App\Modules\Reservation\Actions\CheckOutReservation;
use App\Modules\Reservation\Actions\ConfirmReservation;
use App\Modules\Reservation\Actions\CreateReservation;
use App\Modules\Reservation\Actions\UpdateReservation;
use App\Modules\Reservation\DTOs\ReservationData;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Http\Requests\CheckInReservationRequest;
use App\Modules\Reservation\Http\Requests\CheckOutReservationRequest;
use App\Modules\Reservation\Http\Requests\ConfirmReservationRequest;
use App\Modules\Reservation\Http\Requests\StoreReservationRequest;
use App\Modules\Reservation\Http\Requests\UpdateReservationRequest;
use App\Modules\Reservation\Http\Resources\ReservationResource;
use App\Modules\Reservation\Models\Reservation;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Reservation::class);

        $query = Reservation::query()->with(['guest', 'hotel', 'room', 'roomType']);

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'reservations')
            ->columns([
                Column::make('number', __('Number'))->sortable('id')->searchable()->locked(),
                Column::make('guest', __('Guest'))->searchable('guest.first_name'),
                Column::make('room', __('Room')),
                Column::make('check_in_date', __('Check-in'))->sortable(),
                Column::make('check_out_date', __('Check-out'))->sortable(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('total', __('Total'))->sortable()->align('right'),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(ReservationStatus::class),
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
                Filter::make('booking_source', __('Source'))->fromEnum(BookingSource::class),
            ])
            ->defaultSort('number', 'desc')
            ->transform(fn (Reservation $reservation): array => (new ReservationResource($reservation))->resolve($request));

        return Inertia::render('reservations/index', [
            'table' => $table->toArray(),
            'can' => [
                'create' => Gate::allows('create', Reservation::class),
                'calendar' => Gate::allows('calendar', Reservation::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Reservation::class);

        return Inertia::render('reservations/create', $this->formOptions());
    }

    public function store(StoreReservationRequest $request, CreateReservation $createReservation): RedirectResponse
    {
        $reservation = $createReservation->handle(ReservationData::fromRequest($request));

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('success', __('Reservation :number created.', ['number' => $reservation->number]));
    }

    public function show(Request $request, Reservation $reservation): Response
    {
        Gate::authorize('view', $reservation);

        $reservation->load(['guest', 'hotel', 'room', 'bed', 'roomType', 'paymentMethod', 'guestFolio.invoice']);

        $folio = $reservation->guestFolio;

        return Inertia::render('reservations/show', [
            'reservation' => (new ReservationResource($reservation))->resolve($request),
            'folio' => $folio instanceof GuestFolio
                ? (new GuestFolioResource($folio))->resolve($request)
                : null,
            'can' => [
                'update' => Gate::allows('update', $reservation),
                'cancel' => Gate::allows('cancel', $reservation),
                'confirm' => Gate::allows('update', $reservation),
                'check_in' => Gate::allows('checkIn', $reservation),
                'check_out' => Gate::allows('checkOut', $reservation),
                'view_folio' => Gate::allows('viewAny', GuestFolio::class),
                'open_folio' => $folio === null && Gate::allows('viewAny', GuestFolio::class),
            ],
            'rooms' => $this->roomOptions($reservation->hotel_id),
            'beds' => $this->bedOptions($reservation->hotel_id),
        ]);
    }

    public function edit(Request $request, Reservation $reservation): Response
    {
        Gate::authorize('update', $reservation);

        return Inertia::render('reservations/edit', [
            'reservation' => (new ReservationResource($reservation->load(['guest', 'hotel', 'room', 'bed', 'roomType'])))->resolve($request),
            ...$this->formOptions($reservation->hotel_id),
        ]);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation, UpdateReservation $updateReservation): RedirectResponse
    {
        $updateReservation->handle($reservation, ReservationData::fromRequest($request));

        return back()->with('success', __('Reservation updated.'));
    }

    public function cancel(Reservation $reservation, CancelReservation $cancelReservation): RedirectResponse
    {
        Gate::authorize('cancel', $reservation);
        $cancelReservation->handle($reservation);

        return back()->with('success', __('Reservation cancelled.'));
    }

    public function confirm(ConfirmReservationRequest $request, Reservation $reservation, ConfirmReservation $confirm): RedirectResponse
    {
        $confirm->handle($reservation, $request->validated());

        return back()->with('success', __('Reservation confirmed.'));
    }

    public function checkIn(CheckInReservationRequest $request, Reservation $reservation, CheckInReservation $checkIn): RedirectResponse
    {
        $checkIn->handle($reservation, $request->validated());

        return back()->with('success', __('Guest checked in.'));
    }

    public function checkOut(CheckOutReservationRequest $request, Reservation $reservation, CheckOutReservation $checkOut): RedirectResponse
    {
        $checkOut->handle($reservation, $request->validated());

        return back()->with('success', __('Guest checked out.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(?int $hotelId = null): array
    {
        $hotelId ??= current_hotel_id();

        return [
            'hotels' => $this->hotelOptions(),
            'guests' => $this->guestOptions(),
            'rooms' => $this->roomOptions($hotelId),
            'beds' => $this->bedOptions($hotelId),
            'roomTypes' => $this->roomTypeOptions($hotelId),
            'statuses' => array_values(array_filter(
                ReservationStatus::options(),
                static fn (array $opt): bool => in_array($opt['value'], [
                    ReservationStatus::Pending->value,
                    ReservationStatus::Confirmed->value,
                ], true),
            )),
            'sources' => BookingSource::options(),
            'defaultHotelId' => $hotelId,
        ];
    }

    /** @return array<string, string> */
    protected function hotelOptions(): array
    {
        return Hotel::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')
            ->mapWithKeys(fn ($n, $i): array => [(string) $i => (string) $n])->all();
    }

    /** @return array<string, string> */
    protected function guestOptions(): array
    {
        return Guest::query()->orderBy('last_name')->orderBy('first_name')->get()
            ->mapWithKeys(fn (Guest $g): array => [(string) $g->id => $g->fullName()])->all();
    }

    /** @return array<string, string> */
    protected function roomOptions(?int $hotelId): array
    {
        $q = Room::query()->where('is_active', true)->orderBy('number');
        if ($hotelId !== null) {
            $q->where('hotel_id', $hotelId);
        }

        return $q->pluck('number', 'id')->mapWithKeys(fn ($n, $i): array => [(string) $i => (string) $n])->all();
    }

    /** @return array<string, string> */
    protected function bedOptions(?int $hotelId): array
    {
        $q = Bed::query()->with('room')->where('is_active', true)->orderBy('name');
        if ($hotelId !== null) {
            $q->where('hotel_id', $hotelId);
        }

        return $q->get()->mapWithKeys(
            fn (Bed $b): array => [(string) $b->id => ($b->room?->number ? $b->room->number.' / ' : '').$b->name],
        )->all();
    }

    /** @return array<string, string> */
    protected function roomTypeOptions(?int $hotelId): array
    {
        $q = RoomType::query()->where('is_active', true)->orderBy('name');
        if ($hotelId !== null) {
            $q->where('hotel_id', $hotelId);
        }

        return $q->pluck('name', 'id')->mapWithKeys(fn ($n, $i): array => [(string) $i => (string) $n])->all();
    }
}
