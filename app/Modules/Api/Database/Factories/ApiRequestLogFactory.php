<?php

declare(strict_types=1);

namespace App\Modules\Api\Database\Factories;

use App\Modules\Api\Models\ApiRequestLog;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    protected $model = ApiRequestLog::class;

    /**
     * Every nullable column is defaulted explicitly: Model::shouldBeStrict()
     * turns an unset attribute into an exception rather than a null.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'api_token_id' => null,
            'method' => fake()->randomElement(['GET', 'POST', 'PATCH', 'DELETE']),
            'path' => 'api/v1/users',
            'route_name' => 'api.v1.users.index',
            'status' => 200,
            'duration_ms' => fake()->numberBetween(5, 900),
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'pest/1.0',
            'request_body' => null,
            'response_body' => null,
        ];
    }

    public function failed(int $status = 500): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
