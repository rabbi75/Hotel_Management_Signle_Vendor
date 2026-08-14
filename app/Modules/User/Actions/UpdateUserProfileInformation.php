<?php

declare(strict_types=1);

namespace App\Modules\User\Actions;

use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(protected SecurityLogger $security) {}

    /**
     * @param  User  $user
     * @param  array<string, string|null>  $input
     *
     * @throws ValidationException
     */
    public function update(Authenticatable $user, array $input): void
    {
        $validated = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ])->validateWithBag('updateProfileInformation');

        $emailChanged = $validated['email'] !== $user->email;

        $user->forceFill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'bio' => $validated['bio'] ?? null,
        ]);

        // Changing the address invalidates the previous verification, otherwise
        // an unverified address would inherit the old one's trust.
        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($emailChanged) {
            if ($user instanceof MustVerifyEmail) {
                $user->sendEmailVerificationNotification();
            }

            $this->security->log(SecurityEvent::EmailChanged, $user, __('Account email address changed.'));
        }
    }
}
