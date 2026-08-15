<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Actions\CreateTeam;
use App\Modules\Company\Actions\DeleteTeam;
use App\Modules\Company\Actions\UpdateTeam;
use App\Modules\Company\DTOs\TeamData;
use App\Modules\Company\Http\Controllers\Concerns\ProvidesPickerOptions;
use App\Modules\Company\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Modules\Company\Http\Requests\StoreTeamRequest;
use App\Modules\Company\Http\Requests\UpdateTeamRequest;
use App\Modules\Company\Http\Resources\TeamResource;
use App\Modules\Company\Models\Team;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    use ProvidesPickerOptions, ResolvesWorkspace;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Team::class);

        $query = Team::query()
            ->with(['department', 'lead'])
            ->withCount('members');

        $table = TableBuilder::for($query, $request, 'teams')
            ->columns([
                Column::make('name', __('Name'))->sortable()->searchable()->locked(),
                Column::make('department', __('Department'))->sortable('department_id'),
                Column::make('lead', __('Lead')),
                Column::make('members_count', __('Members'))->align('right'),
                Column::make('created_at', __('Created'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('department_id', __('Department'))->options($this->departmentOptions()),
            ])
            ->defaultSort('name', 'desc')
            ->transform(fn (Team $team): array => (new TeamResource($team))->resolve($request));

        return Inertia::render('teams/index', [
            'table' => $table->toArray(),
            'departments' => $this->departmentOptions(),
            'can' => [
                'create' => Gate::allows('create', Team::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Team::class);

        return Inertia::render('teams/create', [
            'departments' => $this->departmentOptions(),
            'members' => $this->memberOptions(),
        ]);
    }

    public function store(StoreTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $team = $createTeam->handle(TeamData::fromRequest($request));

        return redirect()
            ->route('teams.index')
            ->with('success', __('Team :name created.', ['name' => $team->name]));
    }

    public function show(Request $request, Team $team): Response
    {
        Gate::authorize('view', $team);

        return Inertia::render('teams/show', [
            'team' => (new TeamResource($team->load(['department', 'lead', 'members'])))->resolve($request),
        ]);
    }

    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        return Inertia::render('teams/edit', [
            'team' => (new TeamResource($team->load(['department', 'lead', 'members'])))->resolve($request),
            'departments' => $this->departmentOptions(),
            'members' => $this->memberOptions(),
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team, UpdateTeam $updateTeam): RedirectResponse
    {
        $updateTeam->handle($team, TeamData::fromRequest($request));

        return back()->with('success', __('Team updated.'));
    }

    public function destroy(Team $team, DeleteTeam $deleteTeam): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $deleteTeam->handle($team);

        return redirect()
            ->route('teams.index')
            ->with('success', __('Team deleted.'));
    }
}
