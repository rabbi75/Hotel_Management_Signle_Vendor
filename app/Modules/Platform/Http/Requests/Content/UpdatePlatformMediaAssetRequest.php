<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Media\Http\Requests\UpdateMediaAssetRequest;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class UpdatePlatformMediaAssetRequest extends UpdateMediaAssetRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $this->operatorMayManageContent()
            && $asset instanceof MediaAsset
            && $this->isPlatformOwned($asset);
    }
}
