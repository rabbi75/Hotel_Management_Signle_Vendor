<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PublishPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post && Gate::allows('publish', $post);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Absent means "now"; a date in the past is accepted so a backdated
            // import can be published at its original time.
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
