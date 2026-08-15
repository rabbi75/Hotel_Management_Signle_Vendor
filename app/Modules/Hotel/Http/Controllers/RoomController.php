<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Actions\CreateRoom;
use App\Modules\Hotel\Actions\DeleteRoom;
use App\Modules\Hotel\Actions\UpdateRoom;
use App\Modules\Hotel\DTOs\RoomData;
use App\Modules\Hotel\Enums\RoomStatus;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Hotel\Http\Requests\StoreRoomRequest;
use App\Modules\Hotel\Http\Requests\UpdateRoomRequest;
use App\Modules\Hotel\Http\Resources\RoomResource;
use App\Modules\Hotel\Models\Facility;
use App\Modules\Hotel\Models\Room;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Room::class);

        $query = Room::query()->with(['hotel', 'floor', 'roomType', 'building', 'media'])->withCount('beds');

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'rooms')
            ->columns([
                Column::make('image', __('Photo')),
                Column::make('number', __('Room'))->sortable()->searchable()->locked(),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('room_type', __('Type'))->sortable('room_type_id'),
                Column::make('floor', __('Floor'))->sortable('floor_id'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('beds_count', __('Beds'))->align('right'),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
                Filter::make('status', __('Status'))->fromEnum(RoomStatus::class),
                Filter::make('room_type_id', __('Room type'))->options($this->roomTypeOptions()),
            ])
            ->defaultSort('number', 'asc')
            ->transform(fn (Room $room): array => (new RoomResource($room))->resolve($request));

        return Inertia::render('rooms/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', Room::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Room::class);

        return Inertia::render('rooms/create', [
            'hotels' => $this->hotelOptions(),
            'buildings' => $this->buildingOptions(),
            'floors' => $this->floorOptions(),
            'roomTypes' => $this->roomTypeOptions(),
            'facilities' => $this->facilityOptions(),
            'statuses' => RoomStatus::options(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreRoomRequest $request, CreateRoom $createRoom): RedirectResponse
    {
        $image = $request->file('image');

        $room = $createRoom->handle(
            RoomData::fromRequest($request),
            $image instanceof UploadedFile ? $image : null,
        );

        return redirect()->route('rooms.index')->with('success', __('Room :number created.', ['number' => $room->number]));
    }

    public function edit(Request $request, Room $room): Response
    {
        Gate::authorize('update', $room);

        return Inertia::render('rooms/edit', [
            'room' => (new RoomResource($room->load(['hotel', 'building', 'floor', 'roomType', 'facilities'])))->resolve($request),
            'hotels' => $this->hotelOptions(),
            'buildings' => $this->buildingOptions($room->hotel_id),
            'floors' => $this->floorOptions($room->hotel_id),
            'roomTypes' => $this->roomTypeOptions($room->hotel_id),
            'facilities' => $this->facilityOptions(),
            'statuses' => RoomStatus::options(),
        ]);
    }

    public function update(UpdateRoomRequest $request, Room $room, UpdateRoom $updateRoom): RedirectResponse
    {
        $image = $request->file('image');

        $updateRoom->handle(
            $room,
            RoomData::fromRequest($request),
            $image instanceof UploadedFile ? $image : null,
            $request->boolean('remove_image'),
        );

        return back()->with('success', __('Room updated.'));
    }

    public function destroy(Room $room, DeleteRoom $deleteRoom): RedirectResponse
    {
        Gate::authorize('delete', $room);
        $deleteRoom->handle($room);

        return redirect()->route('rooms.index')->with('success', __('Room deleted.'));
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
