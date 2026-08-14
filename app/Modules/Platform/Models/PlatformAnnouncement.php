<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $admin_id
 * @property string $heading
 * @property string $message
 * @property string $level
 * @property string $audience
 * @property int $recipients_count
 */
class PlatformAnnouncement extends Model
{
    protected $fillable = [
        'admin_id', 'heading', 'message', 'level', 'audience', 'recipients_count', 'sent_at',
    ];

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
