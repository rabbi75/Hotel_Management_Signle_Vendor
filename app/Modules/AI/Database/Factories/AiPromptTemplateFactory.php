<?php

declare(strict_types=1);

namespace App\Modules\AI\Database\Factories;

use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiPromptTemplate>
 */
class AiPromptTemplateFactory extends Factory
{
    protected $model = AiPromptTemplate::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'category' => 'general',
            'prompt' => 'Summarise the following text for {{audience}}: {{content}}',
            'variables' => [
                ['name' => 'audience', 'label' => 'Audience', 'type' => 'text', 'required' => true],
                ['name' => 'content', 'label' => 'Content', 'type' => 'textarea', 'required' => true],
            ],
            'provider' => null,
            'model' => null,
            'is_shared' => false,
            'usage_count' => 0,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes): array => ['is_shared' => true]);
    }
}
