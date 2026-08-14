<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\User\Models\LoginHistory;
use App\Modules\User\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

function agedSecurityLog(int $daysOld): SecurityLog
{
    $log = SecurityLog::create([
        'event' => SecurityEvent::PasswordChanged,
        'severity' => SecurityEvent::PasswordChanged->severity(),
        'description' => 'Password changed',
    ]);

    $log->forceFill(['created_at' => now()->subDays($daysOld)])->save();

    return $log;
}

it('prunes audit records past the configured retention window', function (): void {
    config(['saas.audit.retention_days' => 30, 'saas.auth.login_history_retention_days' => 10]);

    $user = User::factory()->create();

    agedSecurityLog(90);
    $keptLog = agedSecurityLog(5);

    LoginHistory::create([
        'user_id' => $user->id, 'successful' => true, 'logged_in_at' => now()->subDays(60),
    ]);
    $keptLogin = LoginHistory::create([
        'user_id' => $user->id, 'successful' => true, 'logged_in_at' => now()->subDay(),
    ]);

    artisan('audit:prune')->assertSuccessful();

    expect(SecurityLog::query()->pluck('id')->all())->toBe([$keptLog->id])
        ->and(LoginHistory::query()->pluck('id')->all())->toBe([$keptLogin->id]);
});

it('deletes nothing on a dry run', function (): void {
    config(['saas.audit.retention_days' => 1]);

    agedSecurityLog(90);

    artisan('audit:prune --dry-run')->assertSuccessful();

    expect(SecurityLog::query()->count())->toBe(1);
});

it('skips a log whose retention window is not positive', function (): void {
    config(['saas.audit.retention_days' => 0, 'saas.auth.login_history_retention_days' => 0]);

    agedSecurityLog(9000);

    artisan('audit:prune')->assertSuccessful();

    expect(SecurityLog::query()->count())->toBe(1);
});

it('prunes read notifications older than the retention window but keeps unread ones', function (): void {
    config(['saas.notifications.retention_days' => 30]);

    $user = User::factory()->create();

    $make = function (?string $readAt, int $daysOld) use ($user): DatabaseNotification {
        $notification = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'system_announcement',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => ['title' => 'Old news', 'body' => '', 'level' => 'info'],
            'read_at' => $readAt,
        ]);

        $notification->forceFill(['created_at' => now()->subDays($daysOld)])->save();

        return $notification;
    };

    $staleRead = $make(now()->toDateTimeString(), 90);
    $freshRead = $make(now()->toDateTimeString(), 2);
    $staleUnread = $make(null, 90);

    artisan('notifications:prune')->assertSuccessful();

    $remaining = DatabaseNotification::query()->pluck('id')->all();

    expect($remaining)->not->toContain($staleRead->id)
        ->and($remaining)->toContain($freshRead->id)
        ->and($remaining)->toContain($staleUnread->id);
});
