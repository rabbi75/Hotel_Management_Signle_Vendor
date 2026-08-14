<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\CMS;

use App\Modules\CMS\Http\Requests\StorePageRequest;
use App\Modules\Platform\Http\Requests\CMS\Concerns\AuthorizesPlatformPages;

/**
 * The tenant rules, the console's authorisation.
 *
 * `pageRules()` scopes its uniqueness and parent checks by `current_company_id()`,
 * which is null in the console — and Laravel's database rules turn a null value
 * into `whereNull`, so the same rules read "unique among platform pages" here
 * without a single change.
 */
class StorePlatformPageRequest extends StorePageRequest
{
    use AuthorizesPlatformPages;

    public function authorize(): bool
    {
        return $this->operatorMayManagePages();
    }
}
