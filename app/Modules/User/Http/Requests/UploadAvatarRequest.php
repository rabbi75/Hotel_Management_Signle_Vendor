<?php

declare(strict_types=1);

namespace App\Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('update', $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $mimes */
        $mimes = config('saas.uploads.image_mimes');

        return [
            'avatar' => [
                'required',
                'image',
                'mimes:'.implode(',', $mimes),
                'max:'.(int) config('saas.uploads.avatar_max_size_kb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => __('Please choose an image to upload.'),
            'avatar.image' => __('The avatar must be an image file.'),
            'avatar.mimes' => __('Avatars must be one of: :types.', [
                'types' => implode(', ', (array) config('saas.uploads.image_mimes')),
            ]),
            'avatar.max' => __('Avatars may not be larger than :size KB.', [
                'size' => (int) config('saas.uploads.avatar_max_size_kb'),
            ]),
        ];
    }
}
