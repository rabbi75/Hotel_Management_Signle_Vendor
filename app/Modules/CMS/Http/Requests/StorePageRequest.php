<?php

declare(strict_types=1);

namespace App\Modules\CMS\Http\Requests;

use App\Modules\CMS\Http\Requests\Concerns\PageRules;
use App\Modules\CMS\Models\Page;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    use PageRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', Page::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->pageRules();
    }
}
