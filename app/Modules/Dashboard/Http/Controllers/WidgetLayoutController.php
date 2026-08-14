<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Http\Requests\UpdateWidgetLayoutRequest;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Persists one user's drag-and-drop arrangement.
 *
 * The layout is a preference, not workspace data, so it is stored in the `user`
 * settings scope and follows the person between workspaces.
 */
class WidgetLayoutController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function update(UpdateWidgetLayoutRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $this->dashboard->saveLayout($user, $request->layout());

        return back()->with('success', __('Dashboard layout saved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        Gate::authorize('dashboard.customize');

        $this->dashboard->saveLayout($user, []);

        return back()->with('success', __('Dashboard layout reset.'));
    }
}
