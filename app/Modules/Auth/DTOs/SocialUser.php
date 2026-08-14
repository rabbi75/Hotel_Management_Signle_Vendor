<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Support\DTOs\Data;

/**
 * The provider-agnostic shape of an OAuth identity.
 */
readonly class SocialUser extends Data
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name = null,
        public ?string $avatar = null,
        public ?string $token = null,
        public ?string $refreshToken = null,
        public ?int $expiresIn = null,
        public array $raw = [],
    ) {}
}
