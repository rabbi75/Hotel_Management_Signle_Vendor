<?php

declare(strict_types=1);

namespace App\Modules\Company\DTOs;

use App\Modules\Company\Enums\CompanyRole;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

/**
 * A change to one person's membership of a workspace.
 *
 * `role` is the workspace standing (owner/admin/member/guest); `roles` are the
 * RBAC role names — two different systems that happen to share a word.
 */
readonly class MemberData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public CompanyRole $role,
        public array $roles = [],
        public ?int $departmentId = null,
        public ?string $jobTitle = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $roles */
        $roles = array_values(array_filter(
            (array) $request->input('roles', []),
            static fn (mixed $role): bool => is_string($role) && $role !== '',
        ));

        $departmentId = $request->input('department_id');

        return new self(
            role: CompanyRole::from((string) $request->string('role')),
            roles: $roles,
            departmentId: is_numeric($departmentId) ? (int) $departmentId : null,
            jobTitle: $request->string('job_title')->toString() ?: null,
        );
    }

    /**
     * The pivot columns this change writes.
     *
     * @return array<string, mixed>
     */
    public function toPivot(): array
    {
        return [
            'role' => $this->role->value,
            'department_id' => $this->departmentId,
            'job_title' => $this->jobTitle,
        ];
    }
}
