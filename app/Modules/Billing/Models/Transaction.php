<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\TransactionStatus;
use App\Modules\Billing\Enums\TransactionType;
use App\Modules\Billing\Support\Money;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A movement of money, successful or not. The invoice records what is owed;
 * this records what was attempted.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $invoice_id
 * @property string $gateway
 * @property string|null $gateway_id
 * @property TransactionType $type
 * @property TransactionStatus $status
 * @property int $amount
 * @property string $currency
 * @property CarbonImmutable|null $processed_at
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Transaction extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'invoice_id',
        'gateway',
        'gateway_id',
        'type',
        'status',
        'amount',
        'currency',
        'processed_at',
        'failure_reason',
        'meta',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function amountMoney(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount' => 'integer',
            'meta' => 'array',
            'processed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
