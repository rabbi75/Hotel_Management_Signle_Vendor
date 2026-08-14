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
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  User  $user
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(Authenticatable $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => $input['password'],
            'password_changed_at' => now(),
        ])->save();

        $this->security->log(SecurityEvent::PasswordChanged, $user, __('Password updated from the account settings page.'));
    }
}
