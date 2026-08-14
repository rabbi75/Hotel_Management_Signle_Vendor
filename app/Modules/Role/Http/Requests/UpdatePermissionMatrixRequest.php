<?php

declare(strict_types=1);

namespace App\Modules\Role\Http\Requests;

use App\Modules\Role\Models\Permission;
use App\Modules\Role\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole grid arrives in one payload, keyed by role id:
 * `matrix[{role_id}][] = permission name`. A role that is present with an empty
 * array has all of its permissions revoked, which is why the submission has to
 * carry every role the editor was shown.
 */
class UpdatePermissionMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updatePermissions', Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'matrix' => ['required', 'array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['string', Rule::in(Permission::declared())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'matrix.required' => __('No permission changes were submitted.'),
            'matrix.*.*.in' => __('One of the selected permissions is not part of the permission registry.'),
        ];
    }

    /**
     * The submitted grid, with role ids narrowed to editable roles only.
     *
     * @return array<int, list<string>>
     */
    public function matrix(): array
    {
        /** @var array<array-key, array<array-key, string>> $raw */
        $raw = $this->validated('matrix', []);

        $editable = Role::query()
            ->assignable()
            ->whereIn('id', array_map(intval(...), array_keys($raw)))
            ->pluck('id');

        $matrix = [];

        foreach ($editable as $id) {
            $matrix[(int) $id] = array_values(array_unique(array_map(strval(...), $raw[$id] ?? $raw[(string) $id] ?? [])));
        }

        return $matrix;
    }
}
