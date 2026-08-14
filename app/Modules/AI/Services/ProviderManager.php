<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Contracts\AiProvider;
use App\Modules\AI\Exceptions\AiException;
use App\Modules\AI\Providers\AnthropicDriver;
use App\Modules\AI\Providers\DeepSeekDriver;
use App\Modules\AI\Providers\GeminiDriver;
use App\Modules\AI\Providers\GrokDriver;
use App\Modules\AI\Providers\OpenAiDriver;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the configured provider drivers.
 *
 * A driver is only reachable if `saas.ai.providers` declares it, so removing a
 * vendor is a config edit rather than a code change.
 */
class ProviderManager
{
    /** @var array<string, class-string<AiProvider>> */
    protected array $drivers = [
        'anthropic' => AnthropicDriver::class,
        'openai' => OpenAiDriver::class,
        'gemini' => GeminiDriver::class,
        'deepseek' => DeepSeekDriver::class,
        'grok' => GrokDriver::class,
    ];

    /** @var array<string, AiProvider> */
    protected array $resolved = [];

    public function __construct(protected Container $container) {}

    /**
     * @param  class-string<AiProvider>  $driver
     */
    public function extend(string $key, string $driver): void
    {
        $this->drivers[$key] = $driver;
        unset($this->resolved[$key]);
    }

    public function default(): string
    {
        $configured = setting('ai.default_provider');
        $default = is_string($configured) && $configured !== ''
            ? $configured
            : config('saas.ai.default');

        return is_string($default) && $this->supports($default) ? $default : 'anthropic';
    }

    public function supports(string $key): bool
    {
        return isset($this->drivers[$key]) && is_array(config("saas.ai.providers.{$key}"));
    }

    public function driver(?string $key = null): AiProvider
    {
        $key ??= $this->default();

        if (! $this->supports($key)) {
            throw new AiException(__('Unknown AI provider ":key".', ['key' => $key]));
        }

        return $this->resolved[$key] ??= $this->container->make($this->drivers[$key]);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_filter(array_keys($this->drivers), fn (string $key): bool => $this->supports($key)));
    }

    /**
     * @return list<AiProvider>
     */
    public function all(): array
    {
        return array_map(fn (string $key): AiProvider => $this->driver($key), $this->keys());
    }

    /**
     * The catalogue the playground and the provider settings screen render
     * from. Never carries a credential — only whether one is present.
     *
     * @return list<array{key: string, label: string, models: list<array{id: string, label: string}>, is_configured: bool, is_default: bool}>
     */
    public function catalogue(): array
    {
        $default = $this->default();

        return array_map(
            static fn (AiProvider $provider): array => [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'models' => $provider->models(),
                'is_configured' => $provider->isConfigured(),
                'is_default' => $provider->key() === $default,
            ],
            $this->all(),
        );
    }
}
