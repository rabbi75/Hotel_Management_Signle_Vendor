<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Modules\Api\Database\Factories\WebhookDeliveryFactory;
use App\Modules\Api\Enums\WebhookDeliveryStatus;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property int $attempt
 * @property WebhookDeliveryStatus $status
 * @property int|null $status_code
 * @property string|null $response_body
 * @property string|null $error
 * @property int|null $duration_ms
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $next_retry_at
 * @property CarbonImmutable|null $created_at
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'webhook_endpoint_id', 'event', 'payload', 'attempt',
        'status', 'status_code', 'response_body', 'error', 'duration_ms', 'delivered_at', 'next_retry_at',
    ];

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    protected static function newFactory(): WebhookDeliveryFactory
    {
        return WebhookDeliveryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'delivered_at' => 'immutable_datetime',
            'next_retry_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
