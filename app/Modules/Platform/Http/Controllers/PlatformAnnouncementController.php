<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Models\Company;
use App\Modules\Notification\Enums\NotificationLevel;
use App\Modules\Notification\Notifications\SystemAnnouncement;
use App\Modules\Platform\Models\PlatformAnnouncement;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAnnouncementController extends Controller
{
    public function __construct(protected SecurityLogger $security) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.announcements.manage') ?? true, 403);

        $history = PlatformAnnouncement::query()
            ->with('admin:id,name')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(static fn (PlatformAnnouncement $row): array => [
                'id' => $row->id,
                'heading' => $row->heading,
                'message' => $row->message,
                'level' => $row->level,
                'audience' => $row->audience,
                'recipients_count' => $row->recipients_count,
                'admin' => $row->admin?->name,
                'sent_at' => $row->sent_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/announcements/index', [
            'history' => $history,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.announcements.manage') ?? true, 403);

        $validated = $request->validate([
            'heading' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
            'level' => ['required', 'string', 'in:info,success,warning,critical'],
            'audience' => ['required', 'string', 'in:all,active,suspended'],
            'url' => ['nullable', 'url', 'max:500'],
        ]);

        $companies = Company::query()
            ->when($validated['audience'] === 'active', static fn ($q) => $q->where('is_active', true))
            ->when($validated['audience'] === 'suspended', static fn ($q) => $q->where('is_active', false))
            ->with('owner')
            ->get();

        $recipients = $companies
            ->map(static fn (Company $company): ?User => $company->owner)
            ->filter()
            ->unique('id')
            ->values();

        $level = NotificationLevel::tryFrom($validated['level']) ?? NotificationLevel::Info;

        Notification::send(
            $recipients,
            new SystemAnnouncement(
                $validated['heading'],
                $validated['message'],
                $level,
                $validated['url'] ?? null,
                ($validated['url'] ?? null) ? __('Open') : null,
            ),
        );

        PlatformAnnouncement::query()->create([
            'admin_id' => $request->user('admin')?->id,
            'heading' => $validated['heading'],
            'message' => $validated['message'],
            'level' => $validated['level'],
            'audience' => $validated['audience'],
            'recipients_count' => $recipients->count(),
            'sent_at' => now(),
        ]);

        $this->security->log(
            SecurityEvent::AnnouncementBroadcast,
            $request->user('admin'),
            __('Broadcast announcement to :count recipients.', ['count' => $recipients->count()]),
            [
                'heading' => $validated['heading'],
                'audience' => $validated['audience'],
                'recipients' => $recipients->count(),
            ],
        );

        return back()->with('success', __('Announcement sent to :count owner(s).', ['count' => $recipients->count()]));
    }
}
