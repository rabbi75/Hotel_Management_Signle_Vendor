<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\Content;

use App\Modules\Blog\Http\Requests\SchedulePostRequest;
use App\Modules\Blog\Models\Post;
use App\Modules\Platform\Http\Requests\Content\Concerns\AuthorizesPlatformContent;

class SchedulePlatformPostRequest extends SchedulePostRequest
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
