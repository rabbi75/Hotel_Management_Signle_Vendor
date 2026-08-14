<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Blog\Http\Requests\ModerateCommentsRequest;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class ModeratePlatformCommentsRequest extends ModerateCommentsRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        return $this->operatorMayManageContent();
    }
}
