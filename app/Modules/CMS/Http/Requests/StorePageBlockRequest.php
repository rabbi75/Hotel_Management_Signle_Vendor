<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Models\Page;
use App\Modules\CMS\Services\BlockRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePageBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page && Gate::allows('update', $page);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $registry = app(BlockRegistry::class);

        return [
            'type' => ['required', 'string', Rule::in($registry->types())],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['boolean'],
            'data' => ['nullable', 'array'],
        ];
    }
}
