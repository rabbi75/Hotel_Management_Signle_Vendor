<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Company\Enums\CompanyRole;
use App\Modules\Company\Enums\InvitationStatus;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyInvitation>
 */
class CompanyInvitationFactory extends Factory
{
    protected $model = CompanyInvitation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => CompanyRole::Member,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'permission_roles' => null,
            'expires_at' => now()->addDays((int) config('saas.workspace.invitation_expires_days')),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Revoked,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }
}
