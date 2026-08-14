<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Models;

use App\Modules\Hotel\Models\Hotel;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $hotel_id
 * @property bool $is_enabled
 * @property string|null $public_slug
 */
class BookingSetting extends Model
{
    use BelongsToCompany, BelongsToWorkspace;

    protected $fillable = [
        'hotel_id', 'is_enabled', 'public_slug', 'min_advance_days',
        'max_advance_days', 'require_deposit', 'deposit_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'min_advance_days' => 'integer',
            'max_advance_days' => 'integer',
            'require_deposit' => 'boolean',
            'deposit_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
