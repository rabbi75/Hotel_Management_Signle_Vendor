<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderPageBlocksRequest extends FormRequest
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
        $page = $this->route('page');

        return [
            'ids' => ['required', 'array', 'min:1'],

            // Every id must belong to *this* page: a reorder is otherwise a way
            // to move blocks between pages without an update check on either.
            'ids.*' => [
                'integer',
                Rule::exists('page_blocks', 'id')->where('page_id', $page instanceof Page ? $page->id : 0),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = (array) $this->input('ids', []);

        return array_values(array_map(intval(...), $ids));
    }
}
