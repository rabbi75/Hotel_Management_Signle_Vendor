<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\CMS;

use App\Modules\CMS\Http\Requests\UpdatePageBlockRequest;
use App\Modules\Platform\Http\Requests\CMS\Concerns\AuthorizesPlatformPages;

class UpdatePlatformPageBlockRequest extends UpdatePageBlockRequest
{
    use AuthorizesPlatformPages;

    public function authorize(): bool
    {
        return $this->operatorMayManagePages() && $this->blockIsPlatformOwned($this->route('block'));
    }
}
