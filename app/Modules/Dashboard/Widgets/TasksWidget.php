<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * The things this user still has to do.
 *
 * There is no task table in Phase 1, so rather than render an invented backlog
 * the widget derives its items from real state: unfinished account setup and
 * invitations the user sent that nobody has taken up yet. A future Tasks module
 * can register its own widget over the top of this key.
 */
class TasksWidget extends Widget
{
    public function key(): string
    {
        return 'tasks';
    }

    public function title(): string
    {
        return (string) __('Your tasks');
    }

    public function description(): ?string
    {
        return (string) __('Outstanding items for your account and workspace');
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Small;
    }

    public function defaultOrder(): int
    {
        return 60;
    }

    /**
     * Derived from the current user's own state, which the shared cache key
     * already accounts for, but it is cheap enough not to be worth caching.
     */
    public function cacheTtl(): int
    {
        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ['items' => [], 'completed' => 0, 'total' => 0];
        }

        $items = [];

        if ($user->email_verified_at === null) {
            $items[] = $this->task(
                'verify-email',
                (string) __('Verify your email address'),
                'mail-check',
                'verification.notice',
                'high',
            );
        }

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            $items[] = $this->task(
                'enable-two-factor',
                (string) __('Turn on two-factor authentication'),
                'shield-check',
                'settings.two-factor.show',
                'medium',
            );
        }

        $pending = $this->pendingInvitationsSentBy($user);

        if ($pending > 0) {
            $items[] = $this->task(
                'chase-invitations',
                (string) __(':count invitation(s) you sent are still unanswered', ['count' => $pending]),
                'mail-question',
                'companies.invitations.index',
                'low',
            );
        }

        return [
            'items' => $items,
            'completed' => 0,
            'total' => count($items),
        ];
    }

    /**
     * @return array{key: string, label: string, icon: string, url: string|null, priority: string}
     */
    protected function task(string $key, string $label, string $icon, string $route, string $priority): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'url' => Route::has($route) ? route($route) : null,
            'priority' => $priority,
        ];
    }

    protected function pendingInvitationsSentBy(User $user): int
    {
        $companyId = current_company_id();

        if ($companyId === null) {
            return 0;
        }

        return (int) DB::table('company_invitations')
            ->where('company_id', $companyId)
            ->where('invited_by', $user->id)
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->count();
    }
}
