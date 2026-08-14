<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\CMS\Http\Requests\ReorderMenuItemsRequest;
use App\Modules\CMS\Models\Menu;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class ReorderPlatformMenuItemsRequest extends ReorderMenuItemsRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        $menu = $this->route('menu');

        return $this->operatorMayManageContent()
            && $menu instanceof Menu
            && $this->isPlatformOwned($menu);
    }
}
