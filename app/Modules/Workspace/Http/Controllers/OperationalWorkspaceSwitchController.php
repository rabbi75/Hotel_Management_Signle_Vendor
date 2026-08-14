<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Models\User;
use App\Modules\Workspace\Models\Workspace;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Tenancy\CurrentHotel;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperationalWorkspaceSwitchController extends Controller
{
    public function __construct(
        protected CurrentWorkspace $workspace,
        protected CurrentHotel $hotel,
        protected NavigationBuilder $navigation,
        protected SecurityLogger $security,
    ) {}

    public function __invoke(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('switchTo', $workspace);

        /** @var User $user */
        $user = $request->user();

        $sessionKey = (string) config('saas.operations.session_key');
        $request->session()->put($sessionKey, $workspace->id);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->workspace->set($workspace);

        // Property context is workspace-specific; drop it when the boundary moves.
        $request->session()->forget((string) config('saas.hotel.session_key'));
        $this->hotel->forget();

        $this->navigation->flushFor($user);

        $this->security->log(
            SecurityEvent::WorkspaceSwitched,
            $user,
            __('Switched to operational workspace :workspace', ['workspace' => $workspace->name]),
            ['workspace_id' => $workspace->id, 'company_id' => $workspace->company_id],
        );

        return back()->with('success', __('You are now working in :workspace.', ['workspace' => $workspace->name]));
    }
}
