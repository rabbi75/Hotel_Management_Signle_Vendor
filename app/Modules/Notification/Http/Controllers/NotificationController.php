<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Enums\NotificationChannel;
use App\Modules\Notification\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notification\Services\NotificationCenter;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use RuntimeException;

/**
 * The notification inbox.
 *
 * Every action scopes its query through the authenticated user's relation, so
 * ownership is structural rather than a check that could be forgotten.
 */
class NotificationController extends Controller
{
    public function __construct(protected NotificationCenter $center) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return inertia('notifications/index', [
            'table' => $this->center->paginate($user, $request),
            'unread' => $this->center->unreadCount($user),
            'channels' => NotificationChannel::options(),
            'preferences' => Arr::get($user->preferences ?? [], 'notifications', []),
        ]);
    }

    /**
     * The same endpoint serves the bell's "refresh" poll.
     */
    public function preview(Request $request): JsonResponse
    {
        return response()->json($this->center->preview($this->user($request)));
    }

    /**
     * Mark one notification, an explicit set, or everything, as read.
     */
    public function markAsRead(Request $request, ?string $notification = null): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);
        $ids = $this->ids($request, $notification);

        $count = $ids === []
            ? $this->center->markAllAsRead($user)
            : $this->center->markAsRead($user, $ids);

        return $this->respond($request, ['marked' => $count, 'unread' => $this->center->unreadCount($user)]);
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);

        return $this->respond($request, [
            'marked' => $this->center->markAllAsRead($user),
            'unread' => 0,
        ]);
    }

    /**
     * Delete one notification or an explicit set.
     */
    public function destroy(Request $request, ?string $notification = null): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);
        $ids = $this->ids($request, $notification);

        if ($ids === []) {
            throw ValidationException::withMessages(['ids' => __('Select at least one notification.')]);
        }

        return $this->respond($request, [
            'deleted' => $this->center->delete($user, $ids),
            'unread' => $this->center->unreadCount($user),
        ]);
    }

    public function destroyAll(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);

        return $this->respond($request, [
            'deleted' => $this->center->deleteAll($user, $request->boolean('read_only')),
            'unread' => $this->center->unreadCount($user),
        ]);
    }

    /**
     * Per-channel (and per-type) delivery preferences, stored on the user.
     */
    public function preferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $preferences = $user->preferences ?? [];

        Arr::set($preferences, 'notifications.channels', $request->channelPreferences());

        if (($types = $request->typePreferences()) !== []) {
            Arr::set($preferences, 'notifications.types', $types);
        }

        $user->forceFill(['preferences' => $preferences])->save();

        return back()->with('success', __('Notification preferences updated.'));
    }

    /**
     * @return list<string>
     */
    protected function ids(Request $request, ?string $single): array
    {
        if ($single !== null) {
            return [$single];
        }

        /** @var list<mixed> $ids */
        $ids = $request->input('ids', []);

        return array_values(array_map(strval(...), array_filter($ids, is_scalar(...))));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function respond(Request $request, array $payload): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return back();
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new RuntimeException('The notification centre requires an authenticated user.');
        }

        return $user;
    }
}
