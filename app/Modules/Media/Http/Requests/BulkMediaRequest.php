<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMediaRequest extends FormRequest
{
    /**
     * The per-asset policy is still applied in the controller; this only keeps
     * a user with no media rights at all out of the endpoint.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('viewAny', MediaAsset::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['move', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => [
                'integer',
                Rule::exists('media_assets', 'id')
                    ->where('company_id', current_company_id())
                    ->whereNull('deleted_at'),
            ],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('company_id', current_company_id()),
            ],
        ];
    }
}
