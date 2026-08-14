<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $asset = $this->route('asset');

        return $user instanceof User && $asset instanceof MediaAsset && $user->can('update', $asset);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('company_id', current_company_id()),
            ],
        ];
    }
}
