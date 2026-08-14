<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Models\PageBlock;
use App\Modules\CMS\Services\BlockRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePageBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $block = $this->route('block');

        return $block instanceof PageBlock && Gate::allows('update', $block->page()->firstOrFail());
    }

    /**
     * The block's own schema supplies the rules for everything under `data`, so
     * a new block type validates correctly without touching this request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $block = $this->route('block');
        $rules = [
            'data' => ['required', 'array'],
            'is_visible' => ['boolean'],
        ];

        if (! $block instanceof PageBlock) {
            return $rules;
        }

        $schema = app(BlockRegistry::class)->get($block->type);

        return $schema === null ? $rules : [...$rules, ...$schema->rules('data')];
    }
}
