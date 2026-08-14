<?php

declare(strict_types=1);

use App\Modules\Notification\Notifications\SystemAnnouncement;
use App\Modules\Notification\Services\NotificationCenter;
use App\Modules\User\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

/**
 * @param  array<string, mixed>  $data
 */
function notificationFor(User $user, array $data = [], ?string $readAt = null): DatabaseNotification
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
            'level' => 'info',
            'action_url' => null,
            'action_label' => null,
        ], $data),
        'read_at' => $readAt,
    ]);
}

it('redirects a guest to the login screen', function (): void {
    workspace();

    get(route('notifications.index'))->assertRedirect(route('login'));
});

it('forbids a member without the view permission', function (): void {
    $company = workspace();
    $member = memberWith([], $company)->refresh();

    actingAsMember($member, $company)
        ->get(route('notifications.index'), inertiaHeaders())
        ->assertForbidden();
});

it('lists the inbox for the authenticated user only', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();
    $stranger = User::factory()->create();

    notificationFor($member, ['title' => 'Mine']);
    notificationFor($stranger, ['title' => 'Theirs']);

    actingAsMember($member, $company)
        ->get(route('notifications.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'notifications/index')
        ->assertJsonPath('props.unread', 1)
        ->assertJsonPath('props.table.rows.0.title', 'Mine')
        ->assertJsonCount(1, 'props.table.rows');
});

it('marks a single notification as read', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();
    $notification = notificationFor($member);

    actingAsMember($member, $company)
        ->post(route('notifications.read_one', $notification->getKey()))
        ->assertRedirect();

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('marks a selection as read in bulk', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();

    $first = notificationFor($member);
    $second = notificationFor($member);
    $untouched = notificationFor($member);

    actingAsMember($member, $company)
        ->post(route('notifications.read'), ['ids' => [$first->getKey(), $second->getKey()]])
        ->assertRedirect();

    expect(app(NotificationCenter::class)->unreadCount($member->refresh()))->toBe(1)
        ->and($untouched->refresh()->read_at)->toBeNull();
});

it('marks everything as read', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();

    notificationFor($member);
    notificationFor($member);

    actingAsMember($member, $company)
        ->post(route('notifications.read_all'))
        ->assertRedirect();

    expect(app(NotificationCenter::class)->unreadCount($member->refresh()))->toBe(0);
});

it('cannot mark another user\'s notification as read', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();
    $stranger = User::factory()->create();
    $theirs = notificationFor($stranger);

    actingAsMember($member, $company)
        ->post(route('notifications.read_one', $theirs->getKey()))
        ->assertRedirect();

    expect($theirs->refresh()->read_at)->toBeNull();
});

it('deletes a single notification', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();
    $notification = notificationFor($member);

    actingAsMember($member, $company)
        ->delete(route('notifications.destroy', $notification->getKey()))
        ->assertRedirect();

    expect(DatabaseNotification::query()->whereKey($notification->getKey())->exists())->toBeFalse();
});

it('rejects a bulk delete with no selection', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->delete(route('notifications.destroy_bulk'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});

it('empties the inbox', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();

    notificationFor($member);
    notificationFor($member, [], now()->toDateTimeString());

    actingAsMember($member, $company)
        ->delete(route('notifications.destroy_all'))
        ->assertRedirect();

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('stores per-channel delivery preferences', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('notifications.preferences'), [
            'channels' => ['mail' => false, 'broadcast' => true],
            'types' => ['system_announcement' => ['mail' => false]],
        ])
        ->assertRedirect();

    $preferences = $member->refresh()->preferences ?? [];

    expect(data_get($preferences, 'notifications.channels.mail'))->toBeFalse()
        ->and(data_get($preferences, 'notifications.channels.broadcast'))->toBeTrue()
        ->and(data_get($preferences, 'notifications.types.system_announcement.mail'))->toBeFalse();
});

it('rejects a non-boolean channel preference', function (): void {
    $company = workspace();
    $member = memberWith(['notifications.view'], $company)->refresh();

    actingAsMember($member, $company)
        ->put(route('notifications.preferences'), ['channels' => ['mail' => 'maybe']])
        ->assertSessionHasErrors('channels.mail');
});
