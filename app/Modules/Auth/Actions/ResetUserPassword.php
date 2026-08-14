<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Auth\Actions\Concerns\PasswordValidationRules;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  User  $user
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(Authenticatable $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            'password_changed_at' => now(),
        ])->save();

        $this->security->log(SecurityEvent::PasswordResetCompleted, $user, __('Password reset via emailed link.'));
    }
}
