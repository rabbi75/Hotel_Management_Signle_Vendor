<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A webhook the kit has seen.
 *
 * The (gateway, event_id) unique index is the idempotency key: a redelivered
 * event inserts nothing and is acknowledged without being replayed.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class WebhookEvent extends Model
{
    protected $table = 'billing_webhook_events';

    protected $fillable = [
        'gateway',
        'event_id',
        'type',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
