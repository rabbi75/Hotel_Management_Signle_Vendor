<?php

declare(strict_types=1);

namespace App\Modules\Api\Database\Factories;

use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Support\WebhookSignature;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'created_by' => null,
            'url' => 'https://hooks.example.com/'.fake()->uuid(),
            'description' => null,
            'events' => ['user.created'],
            'secret' => WebhookSignature::generateSecret(),
            'is_active' => true,
            'failure_count' => 0,
            'last_success_at' => null,
            'last_failure_at' => null,
            'disabled_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
