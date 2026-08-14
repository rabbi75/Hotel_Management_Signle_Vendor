<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use App\Modules\CMS\Database\Factories\MenuFactory;
use App\Modules\CMS\Enums\MenuLocation;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property MenuLocation $location
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['name', 'location'];

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('order');
    }

    /**
     * The top level of the tree, with two levels of descendants eager loaded —
     * deeper than that is not a navigation menu, it is a sitemap.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['location' => MenuLocation::class];
    }

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }
}
