<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('dashboard/index', [
            'widgets' => $this->dashboard->for($user),
            'available' => $this->dashboard->available($user),
            'can' => [
                'customize' => Gate::allows('dashboard.customize'),
            ],
            // The channel the client subscribes to for DashboardStatsUpdated.
            'channel' => current_company_id() === null ? null : 'company.'.current_company_id(),
        ]);
    }
}
