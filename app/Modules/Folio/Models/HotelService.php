<?php

declare(strict_types=1);

namespace App\Modules\Folio\Models;

use App\Modules\Folio\Enums\HotelServiceCategory;
use App\Modules\Hotel\Models\Hotel;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $hotel_id
 * @property string $name
 * @property string|null $code
 * @property HotelServiceCategory $category
 * @property int $price
 * @property int $tax_rate
 */
class HotelService extends Model
{
    use BelongsToCompany, BelongsToWorkspace;

    protected $fillable = [
        'hotel_id', 'name', 'code', 'category', 'price', 'tax_rate',
        'description', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => HotelServiceCategory::class,
            'price' => 'integer',
            'tax_rate' => 'integer',
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
     * @return HasMany<FolioItem, $this>
     */
    public function folioItems(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }
}
