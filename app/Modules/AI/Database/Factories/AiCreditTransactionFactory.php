<?php

declare(strict_types=1);

namespace App\Modules\AI\Database\Factories;

use App\Modules\AI\Enums\CreditTransactionType;
use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiCreditTransaction;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCreditTransaction>
 */
class AiCreditTransactionFactory extends Factory
{
    protected $model = AiCreditTransaction::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'generation_id' => null,
            'period' => AiCreditBalance::currentPeriod(),
            'type' => CreditTransactionType::Charge,
            'credits' => 5,
            'description' => 'Generation charged.',
        ];
    }
}
