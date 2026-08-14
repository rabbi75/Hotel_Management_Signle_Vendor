<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions\Concerns;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Password rules are centralised on Password::defaults(), which
     * AppServiceProvider tightens in production (12 chars, mixed case, symbols,
     * checked against known breaches).
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }
}
