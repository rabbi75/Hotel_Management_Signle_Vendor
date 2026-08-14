<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Modules\AI\Database\Factories\AiCreditBalanceFactory;
use App\Support\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One workspace's credit envelope for one calendar month.
 *
 * `reserved` is held for in-flight generations and is separate from `used`, so
 * a call that fails releases its hold without ever having spent anything.
 *
 * @property int $id
 * @property int $company_id
 * @property string $period
 * @property int $allowance
 * @property int $used
 * @property int $reserved
 * @property CarbonImmutable|null $created_at
 */
class AiCreditBalance extends Model
{
    /** @use HasFactory<AiCreditBalanceFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'period', 'allowance', 'used', 'reserved'];

    public function available(): int
    {
        return max(0, $this->allowance - $this->used - $this->reserved);
    }

    public static function currentPeriod(): string
    {
        return CarbonImmutable::now()->format('Y-m');
    }

    protected static function newFactory(): AiCreditBalanceFactory
    {
        return AiCreditBalanceFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
