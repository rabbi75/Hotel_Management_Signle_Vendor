<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Http\Requests\Concerns\PostRules;
use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePostRequest extends FormRequest
{
    use PostRules;

    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post && Gate::allows('update', $post);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        return $this->postRules($post instanceof Post ? $post : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->postMessages();
    }
}
