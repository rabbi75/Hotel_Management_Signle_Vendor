<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\User\Enums\UserStatus;
use App\Modules\User\Models\User;
use App\Support\Enums\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Hashing once per process keeps the factory usable in tests that create
     * hundreds of users without paying bcrypt each time.
     */
    protected static ?string $password = null;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'uuid' => (string) Str::ulid(),
            'first_name' => $first,
            'last_name' => $last,
            'name' => "{$first} {$last}",
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'job_title' => fake()->optional()->jobTitle(),
            'bio' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,

            // Set explicitly so the attribute is present on the in-memory model:
            // strict mode throws on reading a column the insert never touched,
            // and SetCurrentCompany reads this on every authenticated request.
            'current_company_id' => null,

            'timezone' => 'UTC',
            'locale' => 'en',
            'theme' => Theme::System,
            'last_login_at' => null,
            'last_login_ip' => null,
            'password_changed_at' => null,
            'suspended_at' => null,
            'suspended_reason' => null,
            'deleted_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspended_reason' => fake()->sentence(),
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt(Str::random(32)),
            'two_factor_recovery_codes' => encrypt(json_encode(
                collect(range(1, 8))->map(fn (): string => Str::random(10).'-'.Str::random(10))->all(),
                JSON_THROW_ON_ERROR,
            )),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
