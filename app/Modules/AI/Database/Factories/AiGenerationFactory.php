<?php

declare(strict_types=1);

namespace App\Modules\AI\Database\Factories;

use App\Modules\AI\Enums\GenerationStatus;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    protected $model = AiGeneration::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'template_id' => null,
            'provider' => 'anthropic',
            'model' => (string) config('saas.ai.providers.anthropic.model'),
            'input' => fake()->sentence(),
            'output' => fake()->paragraph(),
            'prompt_tokens' => 120,
            'completion_tokens' => 240,
            'credits_charged' => 2,
            'duration_ms' => 800,
            'status' => GenerationStatus::Completed,
            'stop_reason' => 'end_turn',
            'error' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GenerationStatus::Failed,
            'output' => null,
            'credits_charged' => 0,
            'error' => 'Provider unavailable.',
        ]);
    }
}
