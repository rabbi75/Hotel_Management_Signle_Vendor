<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Blog\Http\Requests\UpdatePostRequest;
use App\Modules\Blog\Models\Post;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class UpdatePlatformPostRequest extends UpdatePostRequest
{
    use AuthorizesPlatformContent;

    public function authorize(): bool
    {
        $post = $this->route('post');

        return $this->operatorMayManageContent()
            && $post instanceof Post
            && $this->isPlatformOwned($post);
    }
}
