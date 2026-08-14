<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;

class UpdateAvatar
{
    /**
     * @param  UploadedFile|null  $file  Null clears the current avatar.
     */
    public function handle(User $user, ?UploadedFile $file): User
    {
        if (! $file instanceof UploadedFile) {
            $user->clearMediaCollection('avatar');

            return $user;
        }

        // The collection is single-file, so the previous avatar is replaced
        // rather than accumulating orphaned media rows.
        $user->addMedia($file)
            ->usingFileName($user->uuid.'.'.$file->getClientOriginalExtension())
            ->toMediaCollection('avatar');

        return $user->refresh();
    }
}
