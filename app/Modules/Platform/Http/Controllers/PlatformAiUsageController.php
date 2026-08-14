<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\Company\Models\Company;
use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cross-tenant AI spend overview for operators.
 */
class PlatformAiUsageController extends Controller
{
    public function __construct(protected SettingsRepository $settings) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.view') ?? true, 403);

        $period = AiCreditBalance::currentPeriod();
        $periodStart = CarbonImmutable::parse($period.'-01')->startOfMonth();

        $balances = AiCreditBalance::query()
            ->withoutCompanyScope()
            ->where('period', $period)
            ->with(['company:id,uuid,name,is_active'])
            ->orderByDesc('used')
            ->limit(100)
            ->get();

        $generationStats = AiGeneration::query()
            ->withoutCompanyScope()
            ->where('created_at', '>=', $periodStart)
            ->select([
                'company_id',
                DB::raw('count(*) as generations'),
                DB::raw("sum(case when status in ('failed','refused') then 1 else 0 end) as failures"),
            ])
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        $rows = $balances->map(function (AiCreditBalance $balance) use ($generationStats): ?array {
            $company = $balance->company;

            if (! $company instanceof Company) {
                return null;
            }

            $stats = $generationStats->get($company->id);
            $enabled = $this->settings->getFrom(
                SettingsRepository::SCOPE_COMPANY,
                $company->id,
                'ai.enabled',
            );

            if ($enabled === null) {
                $enabled = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'ai.enabled');
            }

            if ($enabled === null) {
                $enabled = (bool) config('saas.ai.enabled', true);
            }

            return [
                'company_id' => $company->id,
                'uuid' => $company->uuid,
                'name' => $company->name,
                'is_active' => $company->is_active,
                'ai_enabled' => (bool) $enabled,
                'period' => $balance->period,
                'allowance' => (int) $balance->allowance,
                'used' => (int) $balance->used,
                'reserved' => (int) $balance->reserved,
                'available' => $balance->available(),
                'generations' => (int) ($stats?->generations ?? 0),
                'failures' => (int) ($stats?->failures ?? 0),
            ];
        })->filter()->values()->all();

        return Inertia::render('admin/ai/usage', [
            'period' => $period,
            'rows' => $rows,
        ]);
    }
}
