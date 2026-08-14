<?php

declare(strict_types=1);

namespace App\Modules\CMS\Policies;

use App\Modules\CMS\Models\Menu;
use App\Modules\User\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.menus.manage');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->inCurrentWorkspace($menu) && $user->can('cms.menus.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.menus.manage');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $this->view($user, $menu);
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $this->view($user, $menu);
    }

    protected function inCurrentWorkspace(Menu $menu): bool
    {
        return $menu->company_id === current_company_id();
    }
}
