<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Actions\CreateFloor;
use App\Modules\Hotel\Actions\DeleteFloor;
use App\Modules\Hotel\Actions\UpdateFloor;
use App\Modules\Hotel\DTOs\FloorData;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Hotel\Http\Requests\StoreFloorRequest;
use App\Modules\Hotel\Http\Requests\UpdateFloorRequest;
use App\Modules\Hotel\Http\Resources\FloorResource;
use App\Modules\Hotel\Models\Floor;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FloorController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Floor::class);

        $query = Floor::query()->with(['hotel', 'building'])->withCount('rooms');

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'floors')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('floor_number', __('Number'))->sortable(),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('building', __('Building'))->sortable('building_id'),
                Column::make('rooms_count', __('Rooms'))->align('right'),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
            ])
            ->defaultSort('floor_number', 'desc')
            ->transform(fn (Floor $floor): array => (new FloorResource($floor))->resolve($request));

        return Inertia::render('floors/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', Floor::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Floor::class);

        return Inertia::render('floors/create', [
            'hotels' => $this->hotelOptions(),
            'buildings' => $this->buildingOptions(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreFloorRequest $request, CreateFloor $createFloor): RedirectResponse
    {
        $floor = $createFloor->handle(FloorData::fromRequest($request));

        return redirect()->route('floors.index')->with('success', __('Floor :name created.', ['name' => $floor->name]));
    }

    public function edit(Request $request, Floor $floor): Response
    {
        Gate::authorize('update', $floor);

        return Inertia::render('floors/edit', [
            'floor' => (new FloorResource($floor->load(['hotel', 'building'])))->resolve($request),
            'hotels' => $this->hotelOptions(),
            'buildings' => $this->buildingOptions($floor->hotel_id),
        ]);
    }

    public function update(UpdateFloorRequest $request, Floor $floor, UpdateFloor $updateFloor): RedirectResponse
    {
        $updateFloor->handle($floor, FloorData::fromRequest($request));

        return back()->with('success', __('Floor updated.'));
    }

    public function destroy(Floor $floor, DeleteFloor $deleteFloor): RedirectResponse
    {
        Gate::authorize('delete', $floor);
        $deleteFloor->handle($floor);

        return redirect()->route('floors.index')->with('success', __('Floor deleted.'));
    }
}
