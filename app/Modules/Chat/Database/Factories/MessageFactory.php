<?php

declare(strict_types=1);

namespace App\Modules\Chat\Database\Factories;

use App\Modules\Chat\Enums\MessageType;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'reply_to_id' => null,
            'type' => MessageType::Text,
            'body' => fake()->sentence(),
            'edited_at' => null,
            'deleted_at' => null,
        ];
    }

    public function inConversation(Conversation $conversation): static
    {
        return $this->state(fn (array $attributes): array => [
            'conversation_id' => $conversation->id,
            'company_id' => $conversation->company_id,
        ]);
    }

    public function from(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => $user->id]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MessageType::System,
            'user_id' => null,
        ]);
    }
}
