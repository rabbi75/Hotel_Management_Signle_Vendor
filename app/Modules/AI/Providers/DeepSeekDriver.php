<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

class DeepSeekDriver extends OpenAiCompatibleDriver
{
    public function key(): string
    {
        return 'deepseek';
    }

    public function label(): string
    {
        return 'DeepSeek';
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function models(): array
    {
        return [
            ['id' => 'deepseek-chat', 'label' => 'DeepSeek Chat'],
            ['id' => 'deepseek-reasoner', 'label' => 'DeepSeek Reasoner'],
        ];
    }
}
