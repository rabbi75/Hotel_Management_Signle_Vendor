<?php

declare(strict_types=1);

namespace App\Modules\HotelPos\Models;

use App\Modules\Hotel\Models\Hotel;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $hotel_id
 * @property string $name
 * @property bool $is_active
 */
class Restaurant extends Model
{
    use BelongsToCompany, BelongsToWorkspace, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'name', 'code', 'description', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return HasMany<PosOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }
}
