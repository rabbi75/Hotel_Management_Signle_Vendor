<?php

declare(strict_types=1);

use App\Modules\Audit\Console\PruneAuditLogsCommand;
use App\Modules\Billing\Console\RenewSubscriptionsCommand;
use App\Modules\Notification\Console\PruneNotificationsCommand;
use Illuminate\Support\Facades\Schedule;
use Spatie\Backup\Commands\BackupCommand;

/*
|------------------------------------------------------------------------------
| Scheduled tasks
|------------------------------------------------------------------------------
|
| Run `php artisan schedule:work` in development, or point cron at
| `php artisan schedule:run` every minute in production.
|
| Everything here is `withoutOverlapping()` because a slow run must never stack
| a second copy on top of the first, and `onOneServer()` so a horizontally
| scaled deployment does not run maintenance jobs N times.
|
*/

Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=336')->daily();
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Housekeeping owned by feature modules. Guarded so removing a module from the
// kit does not break the schedule.
if (class_exists(PruneNotificationsCommand::class)) {
    Schedule::command('notifications:prune')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
}

if (class_exists(PruneAuditLogsCommand::class)) {
    Schedule::command('audit:prune')->dailyAt('02:15')->withoutOverlapping()->onOneServer();
}

if (class_exists(RenewSubscriptionsCommand::class)) {
    // Hourly rather than daily: a trial that matures at 09:00 should convert at
    // 09:00, not at whatever time the nightly sweep happens to run.
    Schedule::command('billing:renew')->hourly()->withoutOverlapping()->onOneServer();
}

if (class_exists(BackupCommand::class)) {
    Schedule::command('backup:clean')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
    Schedule::command('backup:run')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
    Schedule::command('backup:monitor')->dailyAt('04:00')->onOneServer();
}

Schedule::command('telescope:prune --hours=48')->daily();
