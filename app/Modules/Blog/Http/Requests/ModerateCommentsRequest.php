<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A bulk moderation action over a set of comment ids.
 */
class ModerateCommentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Comment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'spam', 'pending', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => [
                'integer',
                // Scoped to the workspace so a crafted id cannot moderate
                // another customer's comments.
                Rule::exists('blog_comments', 'id')->where('company_id', current_company_id()),
            ],
        ];
    }
}
