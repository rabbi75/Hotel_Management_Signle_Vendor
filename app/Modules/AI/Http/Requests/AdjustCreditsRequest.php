<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdjustCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('manage', AiCreditBalance::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'credits' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
