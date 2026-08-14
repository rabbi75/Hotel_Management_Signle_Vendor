<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SchedulePostRequest extends FormRequest
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
            'published_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'published_at.after' => __('A schedule must be in the future. Use "Publish now" to go live immediately.'),
        ];
    }
}
