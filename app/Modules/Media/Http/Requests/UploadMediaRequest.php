<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', MediaAsset::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('saas.media.max_upload_kb', 51200);

        return [
            'files' => ['required', 'array', 'max:25'],
            'files.*' => ['required', 'file', "max:{$maxKb}"],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('company_id', current_company_id()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.max' => __('Each file must be :max KB or smaller.', [
                'max' => (int) config('saas.media.max_upload_kb', 51200),
            ]),
        ];
    }
}
