<?php

declare(strict_types=1);

namespace App\Modules\Folio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $amount
 */
class GuestInvoiceLine extends Model
{
    protected $fillable = [
        'guest_invoice_id', 'description', 'quantity', 'unit_price', 'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GuestInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GuestInvoice::class, 'guest_invoice_id');
    }
}
