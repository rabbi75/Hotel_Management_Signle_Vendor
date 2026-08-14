<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Modules\AI\Database\Factories\AiCreditTransactionFactory;
use App\Modules\AI\Enums\CreditTransactionType;
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
 * @property int|null $generation_id
 * @property string $period
 * @property CreditTransactionType $type
 * @property int $credits
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 */
class AiCreditTransaction extends Model
{
    /** @use HasFactory<AiCreditTransactionFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'user_id', 'generation_id', 'period', 'type', 'credits', 'description'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AiGeneration, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'generation_id');
    }

    protected static function newFactory(): AiCreditTransactionFactory
    {
        return AiCreditTransactionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
