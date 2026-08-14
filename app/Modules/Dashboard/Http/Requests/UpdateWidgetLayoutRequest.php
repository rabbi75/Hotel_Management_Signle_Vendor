<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Requests;

use App\Modules\Dashboard\Enums\WidgetSize;
use App\Modules\Dashboard\Widgets\WidgetRegistry;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWidgetLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('dashboard.customize');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'layout' => ['present', 'array', 'max:50'],
            // Only keys that are actually registered may be stored, so a saved
            // layout can never reference a widget the user cannot see.
            'layout.*.key' => ['required', 'string', Rule::in(app(WidgetRegistry::class)->keys())],
            'layout.*.size' => [
                'required',
                'string',
                Rule::in(array_map(static fn (WidgetSize $size): string => $size->value, WidgetSize::cases())),
            ],
        ];
    }

    /**
     * The validated, ordered layout.
     *
     * @return list<array{key: string, size: string}>
     */
    public function layout(): array
    {
        /** @var list<array{key: string, size: string}> $layout */
        $layout = $this->validated('layout', []);

        return $layout;
    }
}
