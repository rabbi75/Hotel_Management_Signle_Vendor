<?php

declare(strict_types=1);

namespace App\Modules\AI\Exceptions;

class ProviderNotConfiguredException extends AiException
{
    public static function for(string $provider): self
    {
        return new self(__('The :provider provider has no API key configured for this workspace.', ['provider' => $provider]));
    }
}
