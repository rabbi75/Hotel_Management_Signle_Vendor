<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Requests;

use App\Modules\Media\Models\MediaFolder;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $folder = $this->route('folder');

        return $user instanceof User && $folder instanceof MediaFolder && $user->can('update', $folder);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $folder = $this->route('folder');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'parent_id' => [
                'nullable',
                'integer',
                // The self-reference is caught here; a move under a *descendant*
                // needs the tree and is caught in MediaLibraryService.
                Rule::notIn([$folder instanceof MediaFolder ? $folder->id : null]),
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
            'parent_id.not_in' => __('A folder cannot be its own parent.'),
        ];
    }
}
