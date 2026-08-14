<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Month bucketing for the chart widgets.
 *
 * Date formatting is one of the few places where the SQL dialects genuinely
 * differ, so the expression is chosen from the active driver rather than
 * hard-coded to MySQL — the kit runs on MySQL in production and SQLite in the
 * test suite.
 */
trait BucketsByMonth
{
    protected function monthExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => throw new RuntimeException("Month bucketing is not implemented for the [{$driver}] driver."),
        };
    }

    /**
     * A contiguous list of the last $months month buckets, oldest first, so a
     * month with no rows still appears on the chart as a zero.
     *
     * @return list<array{bucket: string, label: string}>
     */
    protected function monthBuckets(int $months): array
    {
        $buckets = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);

            $buckets[] = [
                'bucket' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
            ];
        }

        return $buckets;
    }
}
