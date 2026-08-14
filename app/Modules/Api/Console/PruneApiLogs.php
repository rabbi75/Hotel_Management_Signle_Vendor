<?php

declare(strict_types=1);

namespace App\Modules\Api\Console;

use App\Modules\Api\Models\ApiRequestLog;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PruneApiLogs extends Command
{
    protected $signature = 'api:prune-logs {--days= : Override saas.api.log_retention_days}';

    protected $description = 'Delete API request logs older than the configured retention window.';

    public function handle(): int
    {
        $days = $this->option('days');
        $days = is_numeric($days) ? (int) $days : (int) config('saas.api.log_retention_days');

        if ($days <= 0) {
            $this->components->warn('Retention is disabled (0 days); nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        // Deleted in chunks so a long-neglected installation does not lock the
        // table for the duration of one enormous statement.
        $total = 0;

        do {
            $deleted = ApiRequestLog::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        $this->components->info("Pruned {$total} API request log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
