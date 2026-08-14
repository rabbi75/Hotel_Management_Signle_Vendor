<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\Actions\Concerns\PasswordValidationRules;
use App\Modules\Company\Actions\CreateCompany;
use App\Modules\Company\DTOs\CompanyData;
use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Registers a new account.
 *
 * Registration also provisions the user's first workspace and makes them its
 * owner: the application is multi-tenant everywhere, so a user with no
 * workspace would have nothing to look at.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(protected CreateCompany $createCompany) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        abort_unless((bool) config('saas.auth.registration_enabled'), 403, __('Registration is currently closed.'));

        $validated = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'company_name' => ['required', 'string', 'max:255'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('You must accept the terms of service to continue.'),
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => UserStatus::Active,
                'timezone' => config('saas.defaults.timezone'),
                'locale' => config('saas.defaults.locale'),
            ]);

            $company = $this->createCompany->handle(
                CompanyData::forRegistration($validated['company_name'], $user),
                $user,
            );

            $user->refresh();

            // CreateCompany already sets current_company_id / current_workspace_id.
            if ($user->current_company_id === null) {
                $user->forceFill(['current_company_id' => $company->id])->save();
            }

            return $user;
        });
    }
}
