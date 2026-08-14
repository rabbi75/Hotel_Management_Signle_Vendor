<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use Anthropic\Client;

/**
 * Builds the official Anthropic SDK client.
 *
 * Isolated behind a factory purely so tests can bind a client whose PSR-18
 * transporter is a fake — the driver itself always talks to the real SDK.
 */
class AnthropicClientFactory
{
    public function make(string $apiKey, ?string $baseUrl = null): Client
    {
        return new Client(apiKey: $apiKey, baseUrl: $baseUrl);
    }
}
