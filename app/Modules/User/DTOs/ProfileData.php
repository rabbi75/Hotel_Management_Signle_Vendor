<?php

declare(strict_types=1);

namespace App\Modules\User\DTOs;

use App\Modules\User\Actions\UpdateUserProfileInformation;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

/**
 * The subset of a user record the account owner may edit about themselves.
 */
readonly class ProfileData extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $phone = null,
        public ?string $jobTitle = null,
        public ?string $bio = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            firstName: (string) $request->string('first_name'),
            lastName: (string) $request->string('last_name'),
            email: (string) $request->string('email'),
            phone: $request->string('phone')->toString() ?: null,
            jobTitle: $request->string('job_title')->toString() ?: null,
            bio: $request->string('bio')->toString() ?: null,
        );
    }

    /**
     * Shaped for {@see UpdateUserProfileInformation},
     * which is bound to Fortify's array-input contract.
     *
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'bio' => $this->bio,
        ];
    }
}
