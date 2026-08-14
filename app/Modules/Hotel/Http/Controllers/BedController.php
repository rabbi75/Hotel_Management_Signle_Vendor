<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Actions\CreateBed;
use App\Modules\Hotel\Actions\DeleteBed;
use App\Modules\Hotel\Actions\UpdateBed;
use App\Modules\Hotel\DTOs\BedData;
use App\Modules\Hotel\Enums\BedStatus;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Hotel\Http\Requests\StoreBedRequest;
use App\Modules\Hotel\Http\Requests\UpdateBedRequest;
use App\Modules\Hotel\Http\Resources\BedResource;
use App\Modules\Hotel\Models\Bed;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BedController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bed::class);

        $query = Bed::query()->with(['hotel', 'room']);

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'beds')
            ->columns([
                Column::make('name', __('Bed'))->sortable()->searchable()->locked(),
                Column::make('room', __('Room'))->sortable('room_id'),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('bed_type', __('Type'))->sortable(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('price', __('Price'))->sortable()->align('right'),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
                Filter::make('status', __('Status'))->fromEnum(BedStatus::class),
            ])
            ->defaultSort('name', 'asc')
            ->transform(fn (Bed $bed): array => (new BedResource($bed))->resolve($request));

        return Inertia::render('beds/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', Bed::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Bed::class);

        return Inertia::render('beds/create', [
            'hotels' => $this->hotelOptions(),
            'rooms' => $this->roomOptions(),
            'floors' => $this->floorOptions(),
            'statuses' => BedStatus::options(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreBedRequest $request, CreateBed $createBed): RedirectResponse
    {
        $bed = $createBed->handle(BedData::fromRequest($request));

        return redirect()->route('beds.index')->with('success', __('Bed :name created.', ['name' => $bed->name]));
    }

    public function edit(Request $request, Bed $bed): Response
    {
        Gate::authorize('update', $bed);

        return Inertia::render('beds/edit', [
            'bed' => (new BedResource($bed->load(['hotel', 'room'])))->resolve($request),
            'hotels' => $this->hotelOptions(),
            'rooms' => $this->roomOptions($bed->hotel_id),
            'floors' => $this->floorOptions($bed->hotel_id),
            'statuses' => BedStatus::options(),
        ]);
    }

    public function update(UpdateBedRequest $request, Bed $bed, UpdateBed $updateBed): RedirectResponse
    {
        $updateBed->handle($bed, BedData::fromRequest($request));

        return back()->with('success', __('Bed updated.'));
    }

    public function destroy(Bed $bed, DeleteBed $deleteBed): RedirectResponse
    {
        Gate::authorize('delete', $bed);
        $deleteBed->handle($bed);

        return redirect()->route('beds.index')->with('success', __('Bed deleted.'));
    }
}
