<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Actions\CreateRoomType;
use App\Modules\Hotel\Actions\DeleteRoomType;
use App\Modules\Hotel\Actions\UpdateRoomType;
use App\Modules\Hotel\DTOs\RoomTypeData;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Hotel\Http\Requests\StoreRoomTypeRequest;
use App\Modules\Hotel\Http\Requests\UpdateRoomTypeRequest;
use App\Modules\Hotel\Http\Resources\RoomTypeResource;
use App\Modules\Hotel\Models\Facility;
use App\Modules\Hotel\Models\RoomType;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoomTypeController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RoomType::class);

        $query = RoomType::query()->with('hotel')->withCount('rooms');

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'room-types')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('base_price', __('Base price'))->sortable()->align('right'),
                Column::make('max_occupancy', __('Occupancy'))->sortable()->align('right'),
                Column::make('rooms_count', __('Rooms'))->align('right'),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
            ])
            ->defaultSort('name', 'asc')
            ->transform(fn (RoomType $roomType): array => (new RoomTypeResource($roomType))->resolve($request));

        return Inertia::render('room-types/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', RoomType::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', RoomType::class);

        return Inertia::render('room-types/create', [
            'hotels' => $this->hotelOptions(),
            'facilities' => $this->facilityOptions(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreRoomTypeRequest $request, CreateRoomType $createRoomType): RedirectResponse
    {
        $roomType = $createRoomType->handle(RoomTypeData::fromRequest($request));

        return redirect()->route('room-types.index')->with('success', __('Room type :name created.', ['name' => $roomType->name]));
    }

    public function edit(Request $request, RoomType $roomType): Response
    {
        Gate::authorize('update', $roomType);

        return Inertia::render('room-types/edit', [
            'roomType' => (new RoomTypeResource($roomType->load(['hotel', 'facilities'])))->resolve($request),
            'hotels' => $this->hotelOptions(),
            'facilities' => $this->facilityOptions(),
        ]);
    }

    public function update(UpdateRoomTypeRequest $request, RoomType $roomType, UpdateRoomType $updateRoomType): RedirectResponse
    {
        $updateRoomType->handle($roomType, RoomTypeData::fromRequest($request));

        return back()->with('success', __('Room type updated.'));
    }

    public function destroy(RoomType $roomType, DeleteRoomType $deleteRoomType): RedirectResponse
    {
        Gate::authorize('delete', $roomType);
        $deleteRoomType->handle($roomType);

        return redirect()->route('room-types.index')->with('success', __('Room type deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function facilityOptions(): array
    {
        return Facility::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }
}
