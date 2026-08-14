<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

class GrokDriver extends OpenAiCompatibleDriver
{
    public function key(): string
    {
        return 'grok';
    }

    public function label(): string
    {
        return 'xAI (Grok)';
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function models(): array
    {
        return [
            ['id' => 'grok-2-latest', 'label' => 'Grok 2'],
            ['id' => 'grok-2-vision-latest', 'label' => 'Grok 2 Vision'],
        ];
    }
}
