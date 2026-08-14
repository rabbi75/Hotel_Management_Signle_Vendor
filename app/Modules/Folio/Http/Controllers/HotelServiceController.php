<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Folio\Actions\CreateHotelService;
use App\Modules\Folio\Actions\DeleteHotelService;
use App\Modules\Folio\Actions\UpdateHotelService;
use App\Modules\Folio\DTOs\HotelServiceData;
use App\Modules\Folio\Enums\HotelServiceCategory;
use App\Modules\Folio\Http\Requests\StoreHotelServiceRequest;
use App\Modules\Folio\Http\Requests\UpdateHotelServiceRequest;
use App\Modules\Folio\Http\Resources\HotelServiceResource;
use App\Modules\Folio\Models\HotelService;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HotelServiceController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', HotelService::class);

        $query = HotelService::query()->with('hotel');

        if (current_hotel_id() !== null) {
            $query->where(function ($q): void {
                $q->whereNull('hotel_id')->orWhere('hotel_id', current_hotel_id());
            });
        }

        $table = TableBuilder::for($query, $request, 'hotel-services')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('category', __('Category'))->sortable(),
                Column::make('price', __('Price'))->sortable()->align('right'),
                Column::make('code', __('Code'))->sortable()->searchable()->hidden(),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
                Filter::make('category', __('Category'))->fromEnum(HotelServiceCategory::class)->multiple(),
            ])
            ->defaultSort('name', 'asc')
            ->transform(fn (HotelService $service): array => (new HotelServiceResource($service))->resolve($request));

        return Inertia::render('hotel-services/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', HotelService::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', HotelService::class);

        return Inertia::render('hotel-services/create', [
            'hotels' => $this->hotelOptions(),
            'categories' => HotelServiceCategory::options(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreHotelServiceRequest $request, CreateHotelService $createService): RedirectResponse
    {
        $service = $createService->handle(HotelServiceData::fromRequest($request));

        return redirect()->route('hotel-services.index')->with('success', __('Service :name created.', ['name' => $service->name]));
    }

    public function edit(Request $request, HotelService $hotelService): Response
    {
        Gate::authorize('update', $hotelService);

        return Inertia::render('hotel-services/edit', [
            'service' => (new HotelServiceResource($hotelService->load('hotel')))->resolve($request),
            'hotels' => $this->hotelOptions(),
            'categories' => HotelServiceCategory::options(),
        ]);
    }

    public function update(UpdateHotelServiceRequest $request, HotelService $hotelService, UpdateHotelService $updateService): RedirectResponse
    {
        $updateService->handle($hotelService, HotelServiceData::fromRequest($request));

        return back()->with('success', __('Service updated.'));
    }

    public function destroy(HotelService $hotelService, DeleteHotelService $deleteService): RedirectResponse
    {
        Gate::authorize('delete', $hotelService);
        $deleteService->handle($hotelService);

        return redirect()->route('hotel-services.index')->with('success', __('Service deleted.'));
    }
}
