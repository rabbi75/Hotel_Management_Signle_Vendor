<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Actions\CreateFacility;
use App\Modules\Hotel\Actions\DeleteFacility;
use App\Modules\Hotel\Actions\UpdateFacility;
use App\Modules\Hotel\DTOs\FacilityData;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Hotel\Http\Requests\StoreFacilityRequest;
use App\Modules\Hotel\Http\Requests\UpdateFacilityRequest;
use App\Modules\Hotel\Http\Resources\FacilityResource;
use App\Modules\Hotel\Models\Facility;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FacilityController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Facility::class);

        $query = Facility::query()->with('hotel');

        if (current_hotel_id() !== null) {
            $query->where(function ($q): void {
                $q->whereNull('hotel_id')->orWhere('hotel_id', current_hotel_id());
            });
        }

        $table = TableBuilder::for($query, $request, 'facilities')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('code', __('Code'))->sortable()->searchable(),
                Column::make('icon', __('Icon')),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
            ])
            ->defaultSort('name', 'asc')
            ->transform(fn (Facility $facility): array => (new FacilityResource($facility))->resolve($request));

        return Inertia::render('facilities/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', Facility::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Facility::class);

        return Inertia::render('facilities/create', [
            'hotels' => $this->hotelOptions(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreFacilityRequest $request, CreateFacility $createFacility): RedirectResponse
    {
        $facility = $createFacility->handle(FacilityData::fromRequest($request));

        return redirect()->route('facilities.index')->with('success', __('Facility :name created.', ['name' => $facility->name]));
    }

    public function edit(Request $request, Facility $facility): Response
    {
        Gate::authorize('update', $facility);

        return Inertia::render('facilities/edit', [
            'facility' => (new FacilityResource($facility->load('hotel')))->resolve($request),
            'hotels' => $this->hotelOptions(),
        ]);
    }

    public function update(UpdateFacilityRequest $request, Facility $facility, UpdateFacility $updateFacility): RedirectResponse
    {
        $updateFacility->handle($facility, FacilityData::fromRequest($request));

        return back()->with('success', __('Facility updated.'));
    }

    public function destroy(Facility $facility, DeleteFacility $deleteFacility): RedirectResponse
    {
        Gate::authorize('delete', $facility);
        $deleteFacility->handle($facility);

        return redirect()->route('facilities.index')->with('success', __('Facility deleted.'));
    }
}
