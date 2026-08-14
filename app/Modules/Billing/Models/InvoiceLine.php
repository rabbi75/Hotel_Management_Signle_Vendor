<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on an invoice.
 *
 * Not tenant-scoped in its own right: it is unreachable except through its
 * invoice, which is.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_amount
 * @property int $amount
 * @property string|null $period
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_amount',
        'amount',
        'period',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function amountMoney(string $currency): Money
    {
        return Money::of($this->amount, $currency);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'amount' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
