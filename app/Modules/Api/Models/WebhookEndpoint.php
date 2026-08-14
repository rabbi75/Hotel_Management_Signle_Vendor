<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Modules\Api\Database\Factories\WebhookEndpointFactory;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $created_by
 * @property string $url
 * @property string|null $description
 * @property list<string> $events
 * @property string $secret
 * @property bool $is_active
 * @property int $failure_count
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $last_failure_at
 * @property CarbonImmutable|null $disabled_at
 * @property CarbonImmutable|null $created_at
 */
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * Consecutive failures tolerated before the endpoint is auto-disabled.
     */
    public const FAILURE_THRESHOLD = 10;

    protected $fillable = ['company_id', 'created_by', 'url', 'description', 'events', 'secret', 'is_active'];

    protected $hidden = ['secret'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events, true) || in_array('*', $this->events, true);
    }

    public function isDeliverable(): bool
    {
        return $this->is_active && $this->disabled_at === null;
    }

    protected static function newFactory(): WebhookEndpointFactory
    {
        return WebhookEndpointFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
