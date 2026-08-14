<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperationalWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operations.workspaces.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
