<?php

declare(strict_types=1);

namespace App\Modules\Notification\Console;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--days= : Override the configured retention window}';

    protected $description = 'Delete read notifications older than the configured retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('saas.notifications.retention_days'));

        if ($days < 1) {
            $this->components->warn('Retention window is not positive; nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        // Unread notifications are kept regardless of age: the user has not
        // seen them yet, and silently dropping them loses information.
        $deleted = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->components->info("Pruned {$deleted} read notification(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
