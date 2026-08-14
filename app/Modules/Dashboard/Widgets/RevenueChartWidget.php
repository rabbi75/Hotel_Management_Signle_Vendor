<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\Dashboard\Widgets\Concerns\BucketsByMonth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly revenue.
 *
 * Billing is Phase 2, so the widget reads the billing tables when they exist
 * and otherwise returns an explicitly flagged empty series. It never fabricates
 * a plausible-looking chart: a dashboard that invents numbers is worse than one
 * that admits it has none.
 */
class RevenueChartWidget extends Widget
{
    use BucketsByMonth;

    protected const MONTHS = 12;

    public function key(): string
    {
        return 'revenue-chart';
    }

    public function title(): string
    {
        return (string) __('Revenue');
    }

    public function description(): ?string
    {
        return (string) __('Monthly revenue for the last :months months', ['months' => self::MONTHS]);
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Large;
    }

    public function defaultOrder(): int
    {
        return 20;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $companyId = current_company_id();

        if (! $this->billingAvailable() || $companyId === null) {
            return [
                'available' => false,
                'reason' => (string) __('Billing is not enabled for this installation.'),
                'currency' => (string) config('saas.billing.currency'),
                'series' => [],
                'total' => 0.0,
            ];
        }

        $amounts = DB::table('invoices')
            ->selectRaw($this->monthExpression('created_at').' as bucket, SUM(total) as amount')
            ->where('company_id', $companyId)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->startOfMonth()->subMonths(self::MONTHS - 1))
            ->groupBy('bucket')
            ->pluck('amount', 'bucket');

        $series = [];
        $total = 0.0;

        foreach ($this->monthBuckets(self::MONTHS) as $bucket) {
            $amount = (float) ($amounts[$bucket['bucket']] ?? 0);
            $total += $amount;

            $series[] = [...$bucket, 'value' => round($amount, 2)];
        }

        return [
            'available' => true,
            'reason' => null,
            'currency' => (string) config('saas.billing.currency'),
            'series' => $series,
            'total' => round($total, 2),
        ];
    }

    protected function billingAvailable(): bool
    {
        return (bool) config('saas.billing.enabled') && Schema::hasTable('invoices');
    }
}
