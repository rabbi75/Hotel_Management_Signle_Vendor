<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;
use App\Modules\SEO\Http\Requests\UpdateSeoSettingsRequest;

class UpdatePlatformSeoSettingsRequest extends UpdateSeoSettingsRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        return $this->operatorMayManageContent();
    }
}
