<?php

declare(strict_types=1);

namespace App\Modules\Guest\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Guest\Actions\CreateGuest;
use App\Modules\Guest\Actions\DeleteGuest;
use App\Modules\Guest\Actions\UpdateGuest;
use App\Modules\Guest\DTOs\GuestData;
use App\Modules\Guest\Enums\GuestGender;
use App\Modules\Guest\Http\Requests\StoreGuestRequest;
use App\Modules\Guest\Http\Requests\UpdateGuestRequest;
use App\Modules\Guest\Http\Resources\GuestResource;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Reservation\Http\Resources\ReservationResource;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Guest::class);

        $query = Guest::query()->with('hotel')->withCount('reservations');

        $table = TableBuilder::for($query, $request, 'guests')
            ->columns([
                Column::make('full_name', __('Name'))->sortable('last_name')->searchable('last_name')->locked(),
                Column::make('email', __('Email'))->sortable()->searchable(),
                Column::make('phone', __('Phone'))->searchable(),
                Column::make('is_vip', __('VIP')),
                Column::make('reservations_count', __('Stays'))->align('right'),
            ])
            ->filters([
                Filter::make('is_vip', __('VIP'))->options([
                    '1' => __('Yes'),
                    '0' => __('No'),
                ]),
                Filter::make('is_blacklisted', __('Blacklisted'))->options([
                    '1' => __('Yes'),
                    '0' => __('No'),
                ]),
            ])
            ->defaultSort('full_name', 'desc')
            ->transform(fn (Guest $guest): array => (new GuestResource($guest))->resolve($request));

        return Inertia::render('guests/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', Guest::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Guest::class);

        return Inertia::render('guests/create', [
            'hotels' => $this->hotelOptions(),
            'genders' => GuestGender::options(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreGuestRequest $request, CreateGuest $createGuest): RedirectResponse
    {
        $guest = $createGuest->handle(GuestData::fromRequest($request));

        return redirect()
            ->route('guests.show', $guest)
            ->with('success', __('Guest :name created.', ['name' => $guest->fullName()]));
    }

    public function show(Request $request, Guest $guest): Response
    {
        Gate::authorize('view', $guest);

        $guest->load('hotel')->loadCount('reservations');
        $reservations = $guest->reservations()
            ->with(['hotel', 'room', 'roomType'])
            ->latest('check_in_date')
            ->limit(20)
            ->get();

        return Inertia::render('guests/show', [
            'guest' => (new GuestResource($guest))->resolve($request),
            'reservations' => ReservationResource::collection($reservations)->resolve($request),
            'can' => [
                'update' => Gate::allows('update', $guest),
                'delete' => Gate::allows('delete', $guest),
            ],
        ]);
    }

    public function edit(Request $request, Guest $guest): Response
    {
        Gate::authorize('update', $guest);

        return Inertia::render('guests/edit', [
            'guest' => (new GuestResource($guest->load('hotel')))->resolve($request),
            'hotels' => $this->hotelOptions(),
            'genders' => GuestGender::options(),
        ]);
    }

    public function update(UpdateGuestRequest $request, Guest $guest, UpdateGuest $updateGuest): RedirectResponse
    {
        $updateGuest->handle($guest, GuestData::fromRequest($request));

        return back()->with('success', __('Guest updated.'));
    }

    public function destroy(Guest $guest, DeleteGuest $deleteGuest): RedirectResponse
    {
        Gate::authorize('delete', $guest);
        $deleteGuest->handle($guest);

        return redirect()->route('guests.index')->with('success', __('Guest deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function hotelOptions(): array
    {
        return Hotel::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }
}
