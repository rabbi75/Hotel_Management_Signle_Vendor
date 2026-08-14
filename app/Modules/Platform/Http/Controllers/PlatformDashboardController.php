<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Http\Resources\TenantResource;
use App\Modules\Platform\Services\PlatformMetrics;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    public function __construct(
        protected PlatformMetrics $metrics,
        protected CurrentCompany $tenant,
    ) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.metrics.view') ?? true, 403);

        return Inertia::render('admin/dashboard', [
            'summary' => $this->metrics->summary(),
            'signups' => $this->metrics->signups(),
            'recent' => TenantResource::collection($this->recentTenants())->resolve(),
        ]);
    }

    /**
     * @return Collection<int, Company>
     */
    protected function recentTenants(): mixed
    {
        return $this->tenant->bypass(static fn (): mixed => Company::query()
            ->with(['owner', 'activeSubscription.plan'])
            ->latest('id')
            ->limit(8)
            ->get());
    }
}
