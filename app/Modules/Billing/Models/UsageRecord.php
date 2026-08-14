<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A metered consumption event, e.g. 1,200 AI credits at 14:03.
 *
 * Stored as an append-only ledger rather than a running total so a period can
 * be recomputed after the fact, and so usage can be billed retrospectively.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $subscription_id
 * @property string $metric
 * @property int $quantity
 * @property CarbonImmutable $recorded_at
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UsageRecord extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'subscription_id',
        'metric',
        'quantity',
        'recorded_at',
        'meta',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'meta' => 'array',
            'recorded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
