<?php

declare(strict_types=1);

namespace App\Modules\AI\Database\Factories;

use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCreditBalance>
 */
class AiCreditBalanceFactory extends Factory
{
    protected $model = AiCreditBalance::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'period' => AiCreditBalance::currentPeriod(),
            'allowance' => (int) config('saas.ai.credits.monthly_allowance'),
            'used' => 0,
            'reserved' => 0,
        ];
    }
}
