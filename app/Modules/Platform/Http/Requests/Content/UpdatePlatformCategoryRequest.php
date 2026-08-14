<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Blog\Http\Requests\UpdateCategoryRequest;
use App\Modules\Blog\Models\Category;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class UpdatePlatformCategoryRequest extends UpdateCategoryRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        $category = $this->route('category');

        return $this->operatorMayManageContent()
            && $category instanceof Category
            && $this->isPlatformOwned($category);
    }
}
