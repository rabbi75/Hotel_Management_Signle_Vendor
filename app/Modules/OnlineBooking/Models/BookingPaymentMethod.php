<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Models;

use App\Modules\OnlineBooking\Enums\PaymentMethodDriver;
use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property PaymentMethodDriver $driver
 * @property string $name
 * @property string|null $instructions
 * @property bool $is_enabled
 * @property bool $is_default
 * @property int $sort_order
 */
class BookingPaymentMethod extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'driver',
        'name',
        'instructions',
        'is_enabled',
        'is_default',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => PaymentMethodDriver::class,
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('id');
    }

    public function requiresPrepaid(): bool
    {
        return $this->driver->requiresPrepaid();
    }
}
