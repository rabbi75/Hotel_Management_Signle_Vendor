<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\CMS\Http\Requests\UpdateMenuItemRequest;
use App\Modules\CMS\Models\MenuItem;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class UpdatePlatformMenuItemRequest extends UpdateMenuItemRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        $item = $this->route('item');

        return $this->operatorMayManageContent()
            && $item instanceof MenuItem
            && $this->isPlatformOwned($item);
    }
}
