<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Http\Controllers\Billing\Concerns\ManagesPlatformBilling;
use App\Modules\Platform\Services\RevenueMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the product earns.
 *
 * The one screen the dashboard's single MRR tile was standing in for.
 */
class PlatformRevenueController extends Controller
{
    use ManagesPlatformBilling;

    public function __construct(protected RevenueMetrics $revenue) {}

    public function index(Request $request): Response
    {
        $this->authorizeRevenueRead($request);

        $months = min(24, max(3, $request->integer('months', 12)));

        return Inertia::render('admin/billing/revenue', [
            'overview' => $this->revenue->overview($months),
            'months' => $months,
        ]);
    }
}
