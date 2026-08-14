<?php

declare(strict_types=1);

namespace App\Modules\Housekeeping\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Housekeeping\Actions\AssignHousekeepingTask;
use App\Modules\Housekeeping\Actions\CancelHousekeepingTask;
use App\Modules\Housekeeping\Actions\CompleteHousekeepingTask;
use App\Modules\Housekeeping\Actions\CreateHousekeepingTask;
use App\Modules\Housekeeping\Actions\StartHousekeepingTask;
use App\Modules\Housekeeping\DTOs\HousekeepingTaskData;
use App\Modules\Housekeeping\Enums\HousekeepingPriority;
use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Enums\HousekeepingTaskType;
use App\Modules\Housekeeping\Http\Requests\AssignHousekeepingTaskRequest;
use App\Modules\Housekeeping\Http\Requests\StoreHousekeepingTaskRequest;
use App\Modules\Housekeeping\Http\Resources\HousekeepingTaskResource;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Hotel\Http\Controllers\Concerns\ProvidesHotelOptions;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingTaskController extends Controller
{
    use ProvidesHotelOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', HousekeepingTask::class);

        $query = HousekeepingTask::query()->with(['hotel', 'room', 'assignee', 'reservation']);

        if (current_hotel_id() !== null) {
            $query->where('hotel_id', current_hotel_id());
        }

        $table = TableBuilder::for($query, $request, 'housekeeping-tasks')
            ->columns([
                Column::make('number', __('Task'))->sortable()->searchable()->locked(),
                Column::make('room', __('Room'))->sortable('room_id'),
                Column::make('hotel', __('Hotel'))->sortable('hotel_id'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('priority', __('Priority'))->sortable(),
                Column::make('task_type', __('Type'))->sortable(),
                Column::make('assignee', __('Assigned'))->sortable('assigned_to'),
                Column::make('scheduled_for', __('Scheduled'))->sortable(),
            ])
            ->filters([
                Filter::make('hotel_id', __('Hotel'))->options($this->hotelOptions()),
                Filter::make('status', __('Status'))->fromEnum(HousekeepingTaskStatus::class)->multiple(),
                Filter::make('priority', __('Priority'))->fromEnum(HousekeepingPriority::class)->multiple(),
                Filter::make('task_type', __('Type'))->fromEnum(HousekeepingTaskType::class)->multiple(),
            ])
            ->defaultSort('scheduled_for', 'desc')
            ->transform(fn (HousekeepingTask $task): array => (new HousekeepingTaskResource($task))->resolve($request));

        return Inertia::render('housekeeping/index', [
            'table' => $table->toArray(),
            'can' => ['create' => Gate::allows('create', HousekeepingTask::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', HousekeepingTask::class);

        return Inertia::render('housekeeping/create', [
            'hotels' => $this->hotelOptions(),
            'rooms' => $this->roomOptions(current_hotel_id()),
            'staff' => $this->staffOptions(),
            'priorities' => HousekeepingPriority::options(),
            'taskTypes' => HousekeepingTaskType::options(),
            'defaultHotelId' => current_hotel_id(),
        ]);
    }

    public function store(StoreHousekeepingTaskRequest $request, CreateHousekeepingTask $createTask): RedirectResponse
    {
        $task = $createTask->handle(HousekeepingTaskData::fromRequest($request));

        return redirect()->route('housekeeping.show', $task)->with('success', __('Housekeeping task :number created.', ['number' => $task->number]));
    }

    public function show(Request $request, HousekeepingTask $housekeepingTask): Response
    {
        Gate::authorize('view', $housekeepingTask);

        $housekeepingTask->load(['hotel', 'room', 'assignee', 'reservation']);

        return Inertia::render('housekeeping/show', [
            'task' => (new HousekeepingTaskResource($housekeepingTask))->resolve($request),
            'staff' => $this->staffOptions(),
            'can' => [
                'manage' => Gate::allows('update', $housekeepingTask),
                'assign' => Gate::allows('assign', $housekeepingTask),
                'complete' => Gate::allows('complete', $housekeepingTask),
            ],
        ]);
    }

    public function assign(AssignHousekeepingTaskRequest $request, HousekeepingTask $housekeepingTask, AssignHousekeepingTask $assign): RedirectResponse
    {
        $assignedTo = $request->input('assigned_to');
        $assign->handle($housekeepingTask, is_numeric($assignedTo) ? (int) $assignedTo : null);

        return back()->with('success', __('Task assigned.'));
    }

    public function start(HousekeepingTask $housekeepingTask, StartHousekeepingTask $start): RedirectResponse
    {
        Gate::authorize('update', $housekeepingTask);
        $start->handle($housekeepingTask);

        return back()->with('success', __('Cleaning started.'));
    }

    public function complete(Request $request, HousekeepingTask $housekeepingTask, CompleteHousekeepingTask $complete): RedirectResponse
    {
        Gate::authorize('complete', $housekeepingTask);
        $complete->handle($housekeepingTask, ['notes' => $request->input('notes')]);

        return back()->with('success', __('Room marked clean.'));
    }

    public function cancel(HousekeepingTask $housekeepingTask, CancelHousekeepingTask $cancel): RedirectResponse
    {
        Gate::authorize('update', $housekeepingTask);
        $cancel->handle($housekeepingTask);

        return back()->with('success', __('Task cancelled.'));
    }
}
