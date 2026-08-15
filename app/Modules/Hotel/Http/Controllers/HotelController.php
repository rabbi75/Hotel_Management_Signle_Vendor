<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Actions\CreateHotel;
use App\Modules\Hotel\Actions\DeleteHotel;
use App\Modules\Hotel\Actions\UpdateHotel;
use App\Modules\Hotel\DTOs\HotelData;
use App\Modules\Hotel\Enums\HotelStatus;
use App\Modules\Hotel\Http\Requests\StoreHotelRequest;
use App\Modules\Hotel\Http\Requests\UpdateHotelRequest;
use App\Modules\Hotel\Http\Resources\HotelResource;
use App\Modules\Hotel\Models\Hotel;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Hotel::class);

        $table = TableBuilder::for(Hotel::query()->withCount('rooms'), $request, 'hotels')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('city', __('City'))->sortable()->searchable(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('rooms_count', __('Rooms'))->align('right'),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(HotelStatus::class),
            ])
            ->defaultSort('name', 'desc')
            ->transform(fn (Hotel $hotel): array => (new HotelResource($hotel))->resolve($request));

        return Inertia::render('hotels/index', [
            'table' => $table->toArray(),
            'can' => [
                'create' => Gate::allows('create', Hotel::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Hotel::class);

        return Inertia::render('hotels/create', [
            'statuses' => HotelStatus::options(),
        ]);
    }

    public function store(StoreHotelRequest $request, CreateHotel $createHotel): RedirectResponse
    {
        $hotel = $createHotel->handle(HotelData::fromRequest($request));

        return redirect()
            ->route('hotels.show', $hotel)
            ->with('success', __('Hotel :name created.', ['name' => $hotel->name]));
    }

    public function show(Request $request, Hotel $hotel): Response
    {
        Gate::authorize('view', $hotel);

        $hotel->loadCount(['rooms', 'buildings', 'floors', 'roomTypes']);

        return Inertia::render('hotels/show', [
            'hotel' => (new HotelResource($hotel))->resolve($request),
            'can' => [
                'update' => Gate::allows('update', $hotel),
                'delete' => Gate::allows('delete', $hotel),
                'switch' => Gate::allows('switch', $hotel),
            ],
        ]);
    }

    public function edit(Request $request, Hotel $hotel): Response
    {
        Gate::authorize('update', $hotel);

        return Inertia::render('hotels/edit', [
            'hotel' => (new HotelResource($hotel))->resolve($request),
            'statuses' => HotelStatus::options(),
        ]);
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel, UpdateHotel $updateHotel): RedirectResponse
    {
        $updateHotel->handle($hotel, HotelData::fromRequest($request));

        return back()->with('success', __('Hotel updated.'));
    }

    public function destroy(Hotel $hotel, DeleteHotel $deleteHotel): RedirectResponse
    {
        Gate::authorize('delete', $hotel);

        $name = $hotel->name;
        $deleteHotel->handle($hotel);

        return redirect()
            ->route('hotels.index')
            ->with('success', __('Hotel :name deleted.', ['name' => $name]));
    }
}
