<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Modules\Api\Database\Factories\ApiRequestLogFactory;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $user_id
 * @property int|null $api_token_id
 * @property string $method
 * @property string $path
 * @property string|null $route_name
 * @property int $status
 * @property int $duration_ms
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $request_body
 * @property array<string, mixed>|null $response_body
 * @property CarbonImmutable|null $created_at
 */
class ApiRequestLog extends Model
{
    /** @use HasFactory<ApiRequestLogFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'api_token_id', 'method', 'path', 'route_name',
        'status', 'duration_ms', 'ip_address', 'user_agent', 'request_body', 'response_body',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'api_token_id');
    }

    public function isError(): bool
    {
        return $this->status >= 400;
    }

    protected static function newFactory(): ApiRequestLogFactory
    {
        return ApiRequestLogFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_body' => 'array',
            'response_body' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
