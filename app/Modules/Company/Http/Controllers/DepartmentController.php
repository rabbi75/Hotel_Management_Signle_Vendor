<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Actions\CreateDepartment;
use App\Modules\Company\Actions\DeleteDepartment;
use App\Modules\Company\Actions\UpdateDepartment;
use App\Modules\Company\DTOs\DepartmentData;
use App\Modules\Company\Http\Controllers\Concerns\ProvidesPickerOptions;
use App\Modules\Company\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Modules\Company\Http\Requests\StoreDepartmentRequest;
use App\Modules\Company\Http\Requests\UpdateDepartmentRequest;
use App\Modules\Company\Http\Resources\DepartmentResource;
use App\Modules\Company\Models\Department;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use ProvidesPickerOptions, ResolvesWorkspace;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Department::class);

        $query = Department::query()
            ->with(['parent', 'manager'])
            ->withCount(['teams', 'children']);

        $table = TableBuilder::for($query, $request, 'departments')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('parent', __('Parent'))->sortable('parent_id'),
                Column::make('manager', __('Manager')),
                Column::make('teams_count', __('Teams'))->align('right'),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('parent_id', __('Parent'))->options($this->departmentOptions()),
            ])
            ->defaultSort('name', 'desc')
            ->transform(fn (Department $department): array => (new DepartmentResource($department))->resolve($request));

        return Inertia::render('departments/index', [
            'table' => $table->toArray(),
            'tree' => $this->tree($request),
            'departments' => $this->departmentOptions(),
            'can' => [
                'create' => Gate::allows('create', Department::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Department::class);

        return Inertia::render('departments/create', [
            'departments' => $this->departmentOptions(),
            'members' => $this->memberOptions(),
        ]);
    }

    public function store(StoreDepartmentRequest $request, CreateDepartment $createDepartment): RedirectResponse
    {
        $department = $createDepartment->handle(DepartmentData::fromRequest($request));

        return redirect()
            ->route('departments.index')
            ->with('success', __('Department :name created.', ['name' => $department->name]));
    }

    public function show(Request $request, Department $department): Response
    {
        Gate::authorize('view', $department);

        return Inertia::render('departments/show', [
            'department' => (new DepartmentResource(
                $department->load(['parent', 'manager', 'children'])->loadCount(['teams', 'children']),
            ))->resolve($request),
        ]);
    }

    public function edit(Request $request, Department $department): Response
    {
        Gate::authorize('update', $department);

        return Inertia::render('departments/edit', [
            'department' => (new DepartmentResource($department->load(['parent', 'manager'])))->resolve($request),
            'departments' => $this->departmentOptions($department->id),
            'members' => $this->memberOptions(),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department, UpdateDepartment $updateDepartment): RedirectResponse
    {
        $updateDepartment->handle($department, DepartmentData::fromRequest($request));

        return back()->with('success', __('Department updated.'));
    }

    public function destroy(Department $department, DeleteDepartment $deleteDepartment): RedirectResponse
    {
        Gate::authorize('delete', $department);

        $deleteDepartment->handle($department);

        return redirect()
            ->route('departments.index')
            ->with('success', __('Department deleted.'));
    }

    /**
     * The full hierarchy, for the tree view and the parent picker.
     *
     * @return list<array<string, mixed>>
     */
    protected function tree(Request $request): array
    {
        $roots = Department::query()
            ->whereNull('parent_id')
            ->with('children.children')
            ->orderBy('name')
            ->get();

        return DepartmentResource::collection($roots)->resolve($request);
    }
}
