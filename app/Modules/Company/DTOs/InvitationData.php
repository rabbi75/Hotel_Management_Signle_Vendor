<?php

declare(strict_types=1);

namespace App\Modules\Company\DTOs;

use App\Modules\Company\Enums\CompanyRole;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class InvitationData extends Data
{
    /**
     * @param  list<string>  $permissionRoles  RBAC role names granted on acceptance.
     */
    public function __construct(
        public string $email,
        public CompanyRole $role = CompanyRole::Member,
        public array $permissionRoles = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $roles */
        $roles = array_values(array_filter(
            (array) $request->input('permission_roles', []),
            static fn (mixed $role): bool => is_string($role) && $role !== '',
        ));

        return new self(
            email: mb_strtolower(trim((string) $request->string('email'))),
            role: CompanyRole::from((string) $request->string('role', CompanyRole::Member->value)),
            permissionRoles: $roles,
        );
    }
}
