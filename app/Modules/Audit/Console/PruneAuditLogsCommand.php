<?php

declare(strict_types=1);

namespace App\Modules\Audit\Console;

use App\Modules\Audit\Models\SecurityLog;
use App\Modules\User\Models\LoginHistory;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * Enforces the audit retention policy.
 *
 * Login history has its own, usually shorter, window: it is high volume and
 * mostly noise once an incident window has passed.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete audit records past their configured retention window';

    public function handle(): int
    {
        $auditDays = (int) config('saas.audit.retention_days');
        $loginDays = (int) config('saas.auth.login_history_retention_days');
        $dryRun = (bool) $this->option('dry-run');

        $targets = [
            'activity log' => [Activity::query()->where('created_at', '<', now()->subDays($auditDays)), $auditDays],
            'security log' => [SecurityLog::query()->where('created_at', '<', now()->subDays($auditDays)), $auditDays],
            'login history' => [LoginHistory::query()->where('logged_in_at', '<', now()->subDays($loginDays)), $loginDays],
        ];

        foreach ($targets as $label => [$query, $days]) {
            if ($days < 1) {
                $this->components->warn("Retention for {$label} is not positive; skipped.");

                continue;
            }

            $count = $dryRun ? $query->count() : $query->delete();

            $this->components->info(sprintf(
                '%s %d %s row(s) older than %d day(s).',
                $dryRun ? 'Would prune' : 'Pruned',
                $count,
                $label,
                $days,
            ));
        }

        return self::SUCCESS;
    }
}
