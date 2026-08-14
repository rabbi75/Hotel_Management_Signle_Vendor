<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\User\Models\LoginHistory;
use Illuminate\Support\Facades\DB;

/**
 * The most recent sign-in attempts by members of this workspace, successful or
 * not — a failed attempt is the more interesting of the two.
 */
class RecentLoginsWidget extends Widget
{
    protected const LIMIT = 8;

    public function key(): string
    {
        return 'recent-logins';
    }

    public function title(): string
    {
        return (string) __('Recent sign-ins');
    }

    public function permission(): ?string
    {
        return 'audit.login.view';
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Medium;
    }

    public function defaultOrder(): int
    {
        return 50;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $companyId = current_company_id();

        if ($companyId === null) {
            return ['items' => [], 'failed_recently' => 0];
        }

        $memberIds = DB::table('company_user')->where('company_id', $companyId)->pluck('user_id');

        $entries = LoginHistory::query()
            ->whereIn('user_id', $memberIds)
            ->with('user')
            ->orderByDesc('logged_in_at')
            ->limit(self::LIMIT)
            ->get();

        $failedRecently = LoginHistory::query()
            ->whereIn('user_id', $memberIds)
            ->where('successful', false)
            ->where('logged_in_at', '>=', now()->subDay())
            ->count();

        return [
            'failed_recently' => $failedRecently,
            'items' => $entries->map(static fn (LoginHistory $entry): array => [
                'id' => $entry->id,
                'user' => $entry->user?->name,
                'email' => $entry->email ?? $entry->user?->email,
                'ip_address' => $entry->ip_address,
                'browser' => $entry->browser,
                'platform' => $entry->platform,
                'successful' => $entry->successful,
                'failure_reason' => $entry->failure_reason,
                'logged_in_at' => $entry->logged_in_at->toIso8601String(),
                'logged_in_at_human' => $entry->logged_in_at->diffForHumans(),
            ])->values()->all(),
        ];
    }
}
