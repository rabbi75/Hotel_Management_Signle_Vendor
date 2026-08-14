<?php

declare(strict_types=1);

namespace App\Modules\Chat\Database\Factories;

use App\Modules\Chat\Enums\ConversationType;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Every nullable column is stated explicitly: strict mode throws on reading
     * an attribute the insert never touched.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'created_by' => null,
            'type' => ConversationType::Group,
            'name' => fake()->words(2, true),
            'description' => null,
            'direct_key' => null,
            'last_message_at' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes): array => ['company_id' => $company->id]);
    }

    public function direct(int $userId, int $otherUserId): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ConversationType::Direct,
            'name' => null,
            'direct_key' => Conversation::directKeyFor($userId, $otherUserId),
        ]);
    }

    public function channel(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => ConversationType::Channel]);
    }
}
