<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Platform\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /** Hash once per process — the same optimisation UserFactory uses. */
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::ulid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'avatar_path' => null,
            'status' => 'active',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'suspended']);
    }
}
