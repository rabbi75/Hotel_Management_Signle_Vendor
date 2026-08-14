<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

class OpenAiDriver extends OpenAiCompatibleDriver
{
    public function key(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function models(): array
    {
        return [
            ['id' => 'gpt-4o', 'label' => 'GPT-4o'],
            ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o mini'],
            ['id' => 'gpt-4-turbo', 'label' => 'GPT-4 Turbo'],
        ];
    }
}
