<?php

declare(strict_types=1);

namespace App\Modules\Media\Policies;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\User\Models\User;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media.view');
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $this->inCurrentWorkspace($asset) && $user->can('media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('media.upload');
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $this->inCurrentWorkspace($asset) && $user->can('media.update');
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $this->inCurrentWorkspace($asset) && $user->can('media.delete');
    }

    /**
     * The global scope already constrains queries, but an asset resolved by an
     * explicit id — a picker call, an import — must still be re-checked.
     */
    protected function inCurrentWorkspace(MediaAsset $asset): bool
    {
        return $asset->company_id === current_company_id();
    }
}
