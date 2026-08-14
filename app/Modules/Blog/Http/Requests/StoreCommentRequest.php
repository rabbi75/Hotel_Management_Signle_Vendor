<?php

declare(strict_types=1);

namespace App\Modules\Blog\Http\Requests;

use App\Modules\Blog\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * A comment submitted from the public site.
 *
 * Anyone on the internet can reach this endpoint, so it validates hard: the
 * body length is bounded, a guest must give a plausible name and address, and
 * the honeypot field must stay empty. Rate limiting lives on the route.
 */
class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('saas.blog.comments_enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guest = $this->user() === null;

        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],

            'guest_name' => [$guest ? 'required' : 'prohibited', 'string', 'max:120'],
            'guest_email' => [$guest ? 'required' : 'prohibited', 'email:rfc', 'max:255'],

            // Bots fill every field they are given; humans never see this one.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website.prohibited' => __('That comment could not be accepted.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $post = $this->route('post');

        if ($post instanceof Post && ! $post->allow_comments) {
            throw ValidationException::withMessages([
                'body' => __('Comments are closed on this post.'),
            ]);
        }
    }
}
