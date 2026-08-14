<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use App\Modules\CMS\Enums\LinkTarget;
use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int|null $page_id
 * @property string $label
 * @property string|null $url
 * @property LinkTarget $target
 * @property string|null $icon
 * @property string|null $permission
 * @property int $order
 */
class MenuItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['menu_id', 'parent_id', 'page_id', 'label', 'url', 'target', 'icon', 'permission', 'order'];

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * A linked page wins over a literal URL: if the page is renamed, every menu
     * item pointing at it follows automatically.
     */
    public function resolvedUrl(): string
    {
        $page = $this->relationLoaded('page') ? $this->page : null;

        if ($page instanceof Page) {
            return $page->path();
        }

        return $this->url ?? '#';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => LinkTarget::class,
            'order' => 'integer',
        ];
    }
}
