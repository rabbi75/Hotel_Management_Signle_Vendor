<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Widgets;

use App\Modules\Dashboard\Enums\WidgetSize;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Shortcuts to the things people do most often.
 *
 * Every entry is filtered twice: the route has to exist (its module may not be
 * installed) and the user has to hold the permission behind it.
 */
class QuickActionsWidget extends Widget
{
    public function key(): string
    {
        return 'quick-actions';
    }

    public function title(): string
    {
        return (string) __('Quick actions');
    }

    public function defaultSize(): WidgetSize
    {
        return WidgetSize::Small;
    }

    public function defaultOrder(): int
    {
        return 15;
    }

    /**
     * These are links, not queries — caching them would only serve a stale
     * permission set.
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
        $candidates = [
            ['key' => 'invite-member', 'label' => __('Invite a member'), 'icon' => 'user-plus', 'route' => 'companies.invitations.index', 'permission' => 'companies.members.invite'],
            ['key' => 'view-members', 'label' => __('View members'), 'icon' => 'users-round', 'route' => 'companies.members.index', 'permission' => 'companies.members.view'],
            ['key' => 'new-department', 'label' => __('New department'), 'icon' => 'network', 'route' => 'departments.create', 'permission' => 'companies.departments.manage'],
            ['key' => 'new-team', 'label' => __('New team'), 'icon' => 'users', 'route' => 'teams.create', 'permission' => 'companies.teams.manage'],
            ['key' => 'workspace-settings', 'label' => __('Workspace settings'), 'icon' => 'building-2', 'route' => 'companies.index', 'permission' => 'companies.update'],
            ['key' => 'security', 'label' => __('Security'), 'icon' => 'shield-check', 'route' => 'settings.two-factor.show', 'permission' => null],
        ];

        $actions = [];

        foreach ($candidates as $candidate) {
            $route = (string) $candidate['route'];
            $permission = $candidate['permission'];

            if (! Route::has($route)) {
                continue;
            }

            if (is_string($permission) && ! Gate::allows($permission)) {
                continue;
            }

            $actions[] = [
                'key' => (string) $candidate['key'],
                'label' => (string) $candidate['label'],
                'icon' => (string) $candidate['icon'],
                'url' => route($route),
            ];
        }

        return ['actions' => $actions];
    }
}
