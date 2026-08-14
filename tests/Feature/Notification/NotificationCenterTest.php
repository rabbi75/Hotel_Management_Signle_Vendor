<?php

declare(strict_types=1);

use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Events\NotificationCreated;
use App\Modules\Notification\Notifications\SystemAnnouncement;
use App\Modules\Notification\Services\NotificationCenter;
use App\Modules\User\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $data
 */
function seedNotification(User $user, array $data = [], ?string $readAt = null): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => SystemAnnouncement::class,
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->getKey(),
        'data' => array_merge([
            'key' => 'system_announcement',
            'title' => 'Scheduled maintenance',
            'body' => 'We will be offline briefly.',
            'icon' => 'info',
            'level' => NotificationLevel::Info->value,
            'action_url' => null,
            'action_label' => null,
        ], $data),
        'read_at' => $readAt,
    ]);
}

it('caps the bell preview at the configured count', function (): void {
    config(['saas.notifications.bell_preview_count' => 3]);

    $user = User::factory()->create();

    foreach (range(1, 9) as $index) {
        seedNotification($user, ['title' => "Notice {$index}"]);
    }

    $preview = app(NotificationCenter::class)->preview($user);

    expect($preview['items'])->toHaveCount(3)
        ->and($preview['unread'])->toBe(9);
});

it('returns the newest notifications first in the preview', function (): void {
    config(['saas.notifications.bell_preview_count' => 2]);

    $user = User::factory()->create();

    $old = seedNotification($user, ['title' => 'Older']);
    $old->forceFill(['created_at' => now()->subDay()])->save();

    seedNotification($user, ['title' => 'Newer']);

    $preview = app(NotificationCenter::class)->preview($user);

    expect($preview['items'][0]['title'])->toBe('Newer');
});

it('exposes the full resource shape the bell expects', function (): void {
    $user = User::factory()->create();
    seedNotification($user, ['action_url' => 'https://acme.test/x', 'action_label' => 'Open']);

    $preview = app(NotificationCenter::class)->preview($user);

    expect($preview['items'][0])->toHaveKeys([
        'id', 'type', 'title', 'body', 'icon', 'level',
        'action_url', 'action_label', 'read_at', 'created_at', 'created_at_human',
    ]);
});

it('counts only unread notifications and busts the cache on write', function (): void {
    $user = User::factory()->create();
    $center = app(NotificationCenter::class);

    $first = seedNotification($user);
    seedNotification($user);

    expect($center->unreadCount($user))->toBe(2);

    $center->markAsRead($user, [(string) $first->getKey()]);

    expect($center->unreadCount($user))->toBe(1);

    $center->markAllAsRead($user);

    expect($center->unreadCount($user))->toBe(0);
});

it('deletes notifications only for the given user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = seedNotification($user);
    seedNotification($other);

    $center = app(NotificationCenter::class);

    expect($center->delete($user, [(string) $mine->getKey()]))->toBe(1)
        ->and($center->unreadCount($other))->toBe(1);

    $center->deleteAll($other);

    expect($center->unreadCount($other))->toBe(0);
});

it('keeps unread notifications when deleting read ones only', function (): void {
    $user = User::factory()->create();

    seedNotification($user, [], now()->toDateTimeString());
    seedNotification($user);

    $center = app(NotificationCenter::class);

    expect($center->deleteAll($user, readOnly: true))->toBe(1)
        ->and($center->unreadCount($user))->toBe(1);
});

it('paginates the inbox and filters by read state', function (): void {
    $user = User::factory()->create();

    seedNotification($user, ['title' => 'Unread one']);
    seedNotification($user, ['title' => 'Read one'], now()->toDateTimeString());

    $table = app(NotificationCenter::class)->paginate($user, request()->merge([
        'notifications_filters' => ['status' => 'read'],
    ]));

    expect($table['rows'])->toHaveCount(1)
        ->and($table['rows'][0]['title'])->toBe('Read one');
});

it('broadcasts a created notification with the new unread count', function (): void {
    Event::fake([NotificationCreated::class]);

    $user = User::factory()->create();
    $user->notify(new SystemAnnouncement('Deploy finished', 'Version 2.1 is live.'));

    Event::assertDispatched(
        NotificationCreated::class,
        fn (NotificationCreated $event): bool => $event->userId === $user->id
            && $event->unreadCount === 1
            && $event->notification['title'] === 'Deploy finished',
    );
});

it('respects a per-channel opt out', function (): void {
    $user = User::factory()->create([
        'preferences' => ['notifications' => ['channels' => ['mail' => false, 'broadcast' => false]]],
    ]);

    $channels = (new SystemAnnouncement('Hi', 'There'))->via($user);

    expect($channels)->toBe(['database']);
});

it('respects a per-type opt out that overrides the channel default', function (): void {
    $user = User::factory()->create([
        'preferences' => [
            'notifications' => [
                'channels' => ['mail' => true],
                'types' => ['system_announcement' => ['mail' => false]],
            ],
        ],
    ]);

    expect((new SystemAnnouncement('Hi', 'There'))->via($user))->not->toContain('mail');
});
