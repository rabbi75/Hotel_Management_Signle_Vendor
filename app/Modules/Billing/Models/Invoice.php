<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Support\Money;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $subscription_id
 * @property string $number
 * @property InvoiceStatus $status
 * @property int $subtotal
 * @property int $tax
 * @property int $discount
 * @property int $total
 * @property string $currency
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $paid_at
 * @property string|null $gateway
 * @property string|null $gateway_id
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'subscription_id',
        'number',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'currency',
        'issued_at',
        'due_at',
        'paid_at',
        'gateway',
        'gateway_id',
        'notes',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function totalMoney(): Money
    {
        return Money::of($this->total, $this->currency);
    }

    public function subtotalMoney(): Money
    {
        return Money::of($this->subtotal, $this->currency);
    }

    public function taxMoney(): Money
    {
        return Money::of($this->tax, $this->currency);
    }

    public function discountMoney(): Money
    {
        return Money::of($this->discount, $this->currency);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
