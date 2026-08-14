<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Maintenance\Actions\AssignMaintenanceRequest;
use App\Modules\Maintenance\Actions\CancelMaintenanceRequest;
use App\Modules\Maintenance\Actions\CompleteMaintenanceRequest;
use App\Modules\Maintenance\Actions\CreateMaintenanceRequest;
use App\Modules\Maintenance\Actions\StartMaintenanceRequest;
use App\Modules\Maintenance\Actions\UpdateMaintenanceRequest;
use App\Modules\Maintenance\DTOs\MaintenanceRequestData;
use App\Modules\Maintenance\Enums\MaintenanceCategory;
use App\Modules\Maintenance\Enums\MaintenancePriority;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Http\Requests\StoreMaintenanceRequestRequest;
use App\Modules\Maintenance\Http\Requests\UpdateMaintenanceRequestRequest;
use App\Modules\Maintenance\Http\Resources\MaintenanceRequestResource;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceRequestController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MaintenanceRequest::class);

        $query = MaintenanceRequest::query()->with(['hotel', 'room', 'assignee']);

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'maintenance-requests')
            ->columns([
                Column::make('number', __('Work order'))->sortable()->searchable()->locked(),
                Column::make('title', __('Title'))->sortable()->searchable(),
                Column::make('room', __('Room'))->sortable('room_id'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('priority', __('Priority'))->sortable(),
                Column::make('category', __('Category'))->sortable(),
                Column::make('due_at', __('Due'))->sortable(),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
                Filter::make('status', __('Status'))->fromEnum(MaintenanceRequestStatus::class)->multiple(),
                Filter::make('priority', __('Priority'))->fromEnum(MaintenancePriority::class)->multiple(),
                Filter::make('category', __('Category'))->fromEnum(MaintenanceCategory::class)->multiple(),
            ])
            ->defaultSort('due_at', 'desc')
            ->transform(fn (MaintenanceRequest $workOrder): array => (new MaintenanceRequestResource($workOrder))->resolve($request));

        return Inertia::render('maintenance/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', MaintenanceRequest::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', MaintenanceRequest::class);

        $hotelId = current_hotel_id();

        return Inertia::render('maintenance/create', [
            'hotels' => $this->hotelOptions(),
            'rooms' => $this->roomOptions($hotelId),
            'beds' => $this->bedOptions($hotelId),
            'staff' => $this->staffOptions(),
            'categories' => MaintenanceCategory::options(),
            'priorities' => MaintenancePriority::options(),
            'defaultHotelId' => $hotelId,
        ]);
    }

    public function store(StoreMaintenanceRequestRequest $request, CreateMaintenanceRequest $create): RedirectResponse
    {
        $workOrder = $create->handle(MaintenanceRequestData::fromRequest($request));

        return redirect()->route('maintenance.show', $workOrder)->with('success', __('Work order :number created.', ['number' => $workOrder->number]));
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): Response
    {
        Gate::authorize('view', $maintenanceRequest);

        $maintenanceRequest->load(['hotel', 'room', 'bed', 'assignee', 'reporter']);

        return Inertia::render('maintenance/show', [
            'workOrder' => (new MaintenanceRequestResource($maintenanceRequest))->resolve($request),
            'staff' => $this->staffOptions(),
            'can' => [
                'update' => Gate::allows('update', $maintenanceRequest),
                'assign' => Gate::allows('assign', $maintenanceRequest),
                'complete' => Gate::allows('complete', $maintenanceRequest),
            ],
        ]);
    }

    public function edit(Request $request, MaintenanceRequest $maintenanceRequest): Response
    {
        Gate::authorize('update', $maintenanceRequest);

        return Inertia::render('maintenance/edit', [
            'workOrder' => (new MaintenanceRequestResource($maintenanceRequest->load(['hotel', 'room', 'bed'])))->resolve($request),
            'hotels' => $this->hotelOptions(),
            'rooms' => $this->roomOptions($maintenanceRequest->hotel_id),
            'beds' => $this->bedOptions($maintenanceRequest->hotel_id),
            'staff' => $this->staffOptions(),
            'categories' => MaintenanceCategory::options(),
            'priorities' => MaintenancePriority::options(),
        ]);
    }

    public function update(UpdateMaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest, UpdateMaintenanceRequest $update): RedirectResponse
    {
        $update->handle($maintenanceRequest, MaintenanceRequestData::fromRequest($request));

        return back()->with('success', __('Work order updated.'));
    }

    public function assign(Request $request, MaintenanceRequest $maintenanceRequest, AssignMaintenanceRequest $assign): RedirectResponse
    {
        Gate::authorize('assign', $maintenanceRequest);
        $assignedTo = $request->input('assigned_to');
        $assign->handle($maintenanceRequest, is_numeric($assignedTo) ? (int) $assignedTo : null);

        return back()->with('success', __('Work order assigned.'));
    }

    public function start(MaintenanceRequest $maintenanceRequest, StartMaintenanceRequest $start): RedirectResponse
    {
        Gate::authorize('update', $maintenanceRequest);
        $start->handle($maintenanceRequest);

        return back()->with('success', __('Work started.'));
    }

    public function complete(Request $request, MaintenanceRequest $maintenanceRequest, CompleteMaintenanceRequest $complete): RedirectResponse
    {
        Gate::authorize('complete', $maintenanceRequest);
        $complete->handle($maintenanceRequest, ['resolution_notes' => $request->input('resolution_notes')]);

        return back()->with('success', __('Work order completed.'));
    }

    public function cancel(MaintenanceRequest $maintenanceRequest, CancelMaintenanceRequest $cancel): RedirectResponse
    {
        Gate::authorize('update', $maintenanceRequest);
        $cancel->handle($maintenanceRequest);

        return back()->with('success', __('Work order cancelled.'));
    }

    /** @return array<string, string> */
    protected function bedOptions(?int $hotelId): array
    {
        $query = Bed::query()->with('room')->where('is_active', true)->orderBy('name');

        if ($hotelId !== null) {
            $query->where('hotel_id', $hotelId);
        }

        return $query->get()->mapWithKeys(
            fn (Bed $bed): array => [(string) $bed->id => ($bed->room?->number ? $bed->room->number.' / ' : '').$bed->name],
        )->all();
    }
}
