<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Media\Http\Requests\UpdateMediaFolderRequest;
use App\Modules\Media\Models\MediaFolder;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class UpdatePlatformMediaFolderRequest extends UpdateMediaFolderRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        $folder = $this->route('folder');

        return $this->operatorMayManageContent()
            && $folder instanceof MediaFolder
            && $this->isPlatformOwned($folder);
    }
}
