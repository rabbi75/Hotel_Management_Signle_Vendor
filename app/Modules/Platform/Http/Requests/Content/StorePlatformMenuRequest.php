<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\CMS\Http\Requests\StoreMenuRequest;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class StorePlatformMenuRequest extends StoreMenuRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        return $this->operatorMayManageContent();
    }
}
