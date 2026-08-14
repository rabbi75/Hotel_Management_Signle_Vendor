<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Http\Requests\Concerns\PostRules;
use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePostRequest extends FormRequest
{
    use PostRules;

    public function authorize(): bool
    {
        return Gate::allows('create', Post::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->postRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->postMessages();
    }
}
