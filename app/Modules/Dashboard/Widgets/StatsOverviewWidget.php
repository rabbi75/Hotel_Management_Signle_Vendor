<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\User\Enums\UserStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The headline counters, each with its change against the previous period of
 * the same length so a number is never shown without context.
 */
class StatsOverviewWidget extends Widget
{
    /**
     * Length of the comparison window, in days.
     */
    protected const PERIOD_DAYS = 30;

    public function key(): string
    {
        return 'stats-overview';
    }

    public function title(): string
    {
        return (string) __('Overview');
    }

    public function description(): ?string
    {
        return (string) __('Members and sign-ups over the last :days days', ['days' => self::PERIOD_DAYS]);
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Full;
    }

    public function defaultOrder(): int
    {
        return 10;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $companyId = current_company_id();

        if ($companyId === null) {
            return ['stats' => [], 'period_days' => self::PERIOD_DAYS];
        }

        $periodStart = now()->subDays(self::PERIOD_DAYS);
        $previousStart = now()->subDays(self::PERIOD_DAYS * 2);

        $total = $this->members($companyId)->count();
        $active = $this->members($companyId)->where('users.status', UserStatus::Active->value)->count();

        $current = $this->members($companyId)->where('company_user.joined_at', '>=', $periodStart)->count();
        $previous = $this->members($companyId)
            ->where('company_user.joined_at', '>=', $previousStart)
            ->where('company_user.joined_at', '<', $periodStart)
            ->count();

        $totalBefore = max(0, $total - $current);

        return [
            'period_days' => self::PERIOD_DAYS,
            'stats' => [
                $this->stat('members', (string) __('Members'), $total, $totalBefore, 'users-round'),
                $this->stat('active', (string) __('Active members'), $active, $active, 'user-check'),
                $this->stat('new_signups', (string) __('New sign-ups'), $current, $previous, 'user-plus'),
                $this->stat('pending_invitations', (string) __('Pending invitations'), $this->pendingInvitations($companyId), null, 'mail'),
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, value: int, previous: int|null, delta: float|null, direction: string, icon: string}
     */
    protected function stat(string $key, string $label, int $value, ?int $previous, string $icon): array
    {
        $delta = $this->delta($value, $previous);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'previous' => $previous,
            'delta' => $delta,
            'direction' => match (true) {
                $delta === null || abs($delta) < 0.01 => 'flat',
                $delta > 0 => 'up',
                default => 'down',
            },
            'icon' => $icon,
        ];
    }

    /**
     * Percentage change, or null when there is no baseline to compare against —
     * "up 100%" from zero is noise, not information.
     */
    protected function delta(int $value, ?int $previous): ?float
    {
        if ($previous === null || $previous === 0) {
            return null;
        }

        return round((($value - $previous) / $previous) * 100, 1);
    }

    protected function pendingInvitations(int $companyId): int
    {
        return (int) DB::table('company_invitations')
            ->where('company_id', $companyId)
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->count();
    }

    protected function members(int $companyId): Builder
    {
        return DB::table('company_user')
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->whereNull('users.deleted_at')
            ->where('company_user.company_id', $companyId);
    }
}
