<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Media\Http\Requests\BulkMediaRequest;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class BulkPlatformMediaRequest extends BulkMediaRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        return $this->operatorMayManageContent();
    }
}
