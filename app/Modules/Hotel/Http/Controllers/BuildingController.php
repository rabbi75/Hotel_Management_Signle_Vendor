<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Actions\CreateBuilding;
use App\Modules\Hotel\Actions\DeleteBuilding;
use App\Modules\Hotel\Actions\UpdateBuilding;
use App\Modules\Hotel\DTOs\BuildingData;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Hotel\Http\Requests\StoreBuildingRequest;
use App\Modules\Hotel\Http\Requests\UpdateBuildingRequest;
use App\Modules\Hotel\Http\Resources\BuildingResource;
use App\Modules\Hotel\Models\Building;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Building::class);

        $query = Building::query()->with('hotel')->withCount('floors');

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'buildings')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('code', __('Code'))->sortable()->searchable(),
                Column::make('floors_count', __('Floors'))->align('right'),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
            ])
            ->defaultSort('name', 'asc')
            ->transform(fn (Building $building): array => (new BuildingResource($building))->resolve($request));

        return Inertia::render('buildings/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', Building::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Building::class);

        return Inertia::render('buildings/create', [
            'hotels' => $this->hotelOptions(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreBuildingRequest $request, CreateBuilding $createBuilding): RedirectResponse
    {
        $building = $createBuilding->handle(BuildingData::fromRequest($request));

        return redirect()
            ->route('buildings.index')
            ->with('success', __('Building :name created.', ['name' => $building->name]));
    }

    public function edit(Request $request, Building $building): Response
    {
        Gate::authorize('update', $building);

        return Inertia::render('buildings/edit', [
            'building' => (new BuildingResource($building->load('hotel')))->resolve($request),
            'hotels' => $this->hotelOptions(),
        ]);
    }

    public function update(UpdateBuildingRequest $request, Building $building, UpdateBuilding $updateBuilding): RedirectResponse
    {
        $updateBuilding->handle($building, BuildingData::fromRequest($request));

        return back()->with('success', __('Building updated.'));
    }

    public function destroy(Building $building, DeleteBuilding $deleteBuilding): RedirectResponse
    {
        Gate::authorize('delete', $building);
        $deleteBuilding->handle($building);

        return redirect()->route('buildings.index')->with('success', __('Building deleted.'));
    }
}
