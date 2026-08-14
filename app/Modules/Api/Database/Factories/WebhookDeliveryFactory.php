<?php

declare(strict_types=1);

namespace App\Modules\Api\Database\Factories;

use App\Modules\Api\Enums\WebhookDeliveryStatus;
use App\Modules\Api\Models\WebhookDelivery;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => 'user.created',
            'payload' => ['id' => 1],
            'attempt' => 0,
            'status' => WebhookDeliveryStatus::Pending,
            'status_code' => null,
            'response_body' => null,
            'error' => null,
            'duration_ms' => null,
            'delivered_at' => null,
            'next_retry_at' => null,
        ];
    }
}
