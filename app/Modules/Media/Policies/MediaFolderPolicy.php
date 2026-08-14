<?php

declare(strict_types=1);

namespace App\Modules\Media\Policies;

use App\Modules\Media\Models\MediaFolder;
use App\Modules\User\Models\User;

class MediaFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media.view');
    }

    public function view(User $user, MediaFolder $folder): bool
    {
        return $this->inCurrentWorkspace($folder) && $user->can('media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('media.folders.manage');
    }

    public function update(User $user, MediaFolder $folder): bool
    {
        return $this->inCurrentWorkspace($folder) && $user->can('media.folders.manage');
    }

    public function delete(User $user, MediaFolder $folder): bool
    {
        return $this->update($user, $folder);
    }

    protected function inCurrentWorkspace(MediaFolder $folder): bool
    {
        return $folder->company_id === current_company_id();
    }
}
