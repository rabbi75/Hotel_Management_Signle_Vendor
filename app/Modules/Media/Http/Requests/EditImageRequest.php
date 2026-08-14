<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class EditImageRequest extends FormRequest
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
            'rotate' => ['nullable', 'integer', 'min:-360', 'max:360'],
            'crop_x' => ['nullable', 'integer', 'min:0'],
            'crop_y' => ['nullable', 'integer', 'min:0'],
            'crop_width' => ['nullable', 'integer', 'min:1', 'max:20000', 'required_with:crop_height'],
            'crop_height' => ['nullable', 'integer', 'min:1', 'max:20000', 'required_with:crop_width'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'quality' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
