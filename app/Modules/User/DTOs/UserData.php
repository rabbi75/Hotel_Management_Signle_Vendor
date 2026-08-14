<?php

declare(strict_types=1);

namespace App\Modules\User\DTOs;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\User\Enums\UserStatus;
use App\Support\DTOs\Data;
use App\Support\Enums\Theme;
use Illuminate\Http\Request;

/**
 * Everything an administrator may set on another user's account.
 *
 * Deliberately does not carry `status` transitions such as suspension: those go
 * through their own actions so they are always audited.
 */
readonly class UserData extends Data
{
    /**
     * @param  list<string>|null  $roles  Null means "leave role assignments untouched".
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $password = null,
        public ?string $phone = null,
        public ?string $jobTitle = null,
        public ?string $bio = null,
        public UserStatus $status = UserStatus::Active,
        public CompanyRole $companyRole = CompanyRole::Member,
        public ?array $roles = null,
        public string $timezone = 'UTC',
        public string $locale = 'en',
        public Theme $theme = Theme::System,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string>|null $roles */
        $roles = $request->has('roles') ? array_values(array_map(strval(...), (array) $request->input('roles', []))) : null;

        return new self(
            firstName: (string) $request->string('first_name'),
            lastName: (string) $request->string('last_name'),
            email: (string) $request->string('email'),
            password: $request->string('password')->toString() ?: null,
            phone: $request->string('phone')->toString() ?: null,
            jobTitle: $request->string('job_title')->toString() ?: null,
            bio: $request->string('bio')->toString() ?: null,
            status: UserStatus::tryFrom((string) $request->string('status')) ?? UserStatus::Active,
            companyRole: CompanyRole::tryFrom((string) $request->string('company_role')) ?? CompanyRole::Member,
            roles: $roles,
            timezone: $request->string('timezone', (string) config('saas.defaults.timezone'))->toString(),
            locale: $request->string('locale', (string) config('saas.defaults.locale'))->toString(),
            theme: Theme::tryFrom((string) $request->string('theme')) ?? Theme::System,
        );
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'name' => $this->fullName(),
            'email' => $this->email,
            'password' => $this->password,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'bio' => $this->bio,
            'status' => $this->status,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'theme' => $this->theme,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Attributes for an edit: the optional profile fields are kept even when
     * null, because clearing them is a deliberate act rather than an omission.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        return [
            ...$this->toAttributes(),
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'bio' => $this->bio,
        ];
    }
}
