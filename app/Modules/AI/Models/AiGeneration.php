<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Modules\AI\Database\Factories\AiGenerationFactory;
use App\Modules\AI\Enums\GenerationStatus;
use App\Modules\User\Models\User;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property int|null $template_id
 * @property string $provider
 * @property string $model
 * @property string $input
 * @property string|null $output
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $credits_charged
 * @property int $duration_ms
 * @property GenerationStatus $status
 * @property string|null $stop_reason
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 */
class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'template_id', 'provider', 'model', 'input', 'output',
        'prompt_tokens', 'completion_tokens', 'credits_charged', 'duration_ms', 'status', 'stop_reason', 'error',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AiPromptTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AiPromptTemplate::class, 'template_id');
    }

    public function totalTokens(): int
    {
        return $this->prompt_tokens + $this->completion_tokens;
    }

    protected static function newFactory(): AiGenerationFactory
    {
        return AiGenerationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
