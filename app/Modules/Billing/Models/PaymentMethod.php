<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored payment instrument.
 *
 * Only the non-sensitive display fields live here — brand, last four, expiry.
 * The instrument itself is always held by the gateway; the kit never sees, and
 * must never store, a full card number.
 *
 * @property int $id
 * @property int $company_id
 * @property string $gateway
 * @property string $gateway_id
 * @property string $type
 * @property string|null $brand
 * @property string|null $last_four
 * @property int|null $exp_month
 * @property int|null $exp_year
 * @property string|null $holder_name
 * @property bool $is_default
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PaymentMethod extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'gateway',
        'gateway_id',
        'type',
        'brand',
        'last_four',
        'exp_month',
        'exp_year',
        'holder_name',
        'is_default',
    ];

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        if ($this->exp_year === null || $this->exp_month === null) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        return CarbonImmutable::create($this->exp_year, $this->exp_month, 1)
            ->endOfMonth()
            ->lessThan($now);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'is_default' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
