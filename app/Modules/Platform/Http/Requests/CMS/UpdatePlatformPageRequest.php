<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\CMS;

use App\Modules\CMS\Http\Requests\UpdatePageRequest;
use App\Modules\Platform\Http\Requests\CMS\Concerns\AuthorizesPlatformPages;

class UpdatePlatformPageRequest extends UpdatePageRequest
{
    use AuthorizesPlatformPages;

    public function authorize(): bool
    {
        return $this->operatorMayManagePages() && $this->isPlatformOwned($this->route('page'));
    }
}
