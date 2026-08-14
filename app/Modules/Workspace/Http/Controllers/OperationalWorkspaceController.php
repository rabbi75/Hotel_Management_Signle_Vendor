<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workspace\Actions\CreateWorkspace;
use App\Modules\Workspace\DTOs\WorkspaceData;
use App\Modules\Workspace\Http\Requests\StoreOperationalWorkspaceRequest;
use App\Modules\Workspace\Http\Resources\WorkspaceSummaryResource;
use App\Modules\Workspace\Models\Workspace;
use App\Modules\Workspace\Services\AccessibleWorkspaces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OperationalWorkspaceController extends Controller
{
    public function index(Request $request, AccessibleWorkspaces $accessible): Response
    {
        Gate::authorize('viewAny', Workspace::class);

        $workspaces = $accessible->forUser($request->user());

        return Inertia::render('operational-workspaces/index', [
            'workspaces' => WorkspaceSummaryResource::collection($workspaces)->resolve(),
            'current' => current_workspace()?->uuid,
            'can' => [
                'create' => Gate::allows('create', Workspace::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Workspace::class);

        return Inertia::render('operational-workspaces/create', [
            'defaults' => [
                'timezone' => current_company()?->timezone ?? config('saas.defaults.timezone'),
                'currency' => current_company()?->currency ?? config('saas.defaults.currency'),
            ],
        ]);
    }

    public function store(StoreOperationalWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $company = current_company();

        abort_if($company === null, 403);

        $workspace = $createWorkspace->handle(
            $company,
            WorkspaceData::fromRequest($request),
            $request->user(),
        );

        $request->session()->put((string) config('saas.operations.session_key'), $workspace->id);
        $request->user()?->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()
            ->route('dashboard')
            ->with('success', __('Operational workspace :name created.', ['name' => $workspace->name]));
    }
}
