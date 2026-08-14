<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $page_id
 * @property string $type
 * @property int $order
 * @property array<string, mixed> $data
 * @property bool $is_visible
 */
class PageBlock extends Model
{
    use BelongsToCompany;

    protected $fillable = ['page_id', 'type', 'order', 'data', 'is_visible'];

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_visible' => 'boolean',
            'order' => 'integer',
        ];
    }
}
