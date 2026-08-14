<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\CMS;

use App\Modules\CMS\Http\Requests\StorePageBlockRequest;
use App\Modules\Platform\Http\Requests\CMS\Concerns\AuthorizesPlatformPages;

class StorePlatformPageBlockRequest extends StorePageBlockRequest
{
    use AuthorizesPlatformPages;

    public function authorize(): bool
    {
        return $this->operatorMayManagePages() && $this->isPlatformOwned($this->route('page'));
    }
}
