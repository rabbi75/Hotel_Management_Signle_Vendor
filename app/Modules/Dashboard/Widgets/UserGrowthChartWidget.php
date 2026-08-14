<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\Dashboard\Widgets\Concerns\BucketsByMonth;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * How the workspace's membership has grown, month by month.
 */
class UserGrowthChartWidget extends Widget
{
    use BucketsByMonth;

    protected const MONTHS = 12;

    public function key(): string
    {
        return 'user-growth-chart';
    }

    public function title(): string
    {
        return (string) __('Member growth');
    }

    public function description(): ?string
    {
        return (string) __('New members joined each month');
    }

    public function permission(): ?string
    {
        return 'companies.members.view';
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Medium;
    }

    public function defaultOrder(): int
    {
        return 30;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $companyId = current_company_id();

        if ($companyId === null) {
            return ['series' => [], 'total' => 0, 'cumulative' => 0];
        }

        $since = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $counts = DB::table('company_user')
            ->selectRaw($this->monthExpression('joined_at').' as bucket, COUNT(*) as total')
            ->where('company_id', $companyId)
            ->whereNotNull('joined_at')
            ->where('joined_at', '>=', $since)
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        // Everyone who joined before the window, so the running total on the
        // chart is the real headcount rather than a partial one.
        $running = (int) DB::table('company_user')
            ->where('company_id', $companyId)
            ->where(function (QueryBuilder $query) use ($since): void {
                $query->whereNull('joined_at')->orWhere('joined_at', '<', $since);
            })
            ->count();

        $series = [];
        $joined = 0;

        foreach ($this->monthBuckets(self::MONTHS) as $bucket) {
            $value = (int) ($counts[$bucket['bucket']] ?? 0);
            $joined += $value;
            $running += $value;

            $series[] = [...$bucket, 'value' => $value, 'cumulative' => $running];
        }

        return [
            'series' => $series,
            'total' => $joined,
            'cumulative' => $running,
        ];
    }
}
