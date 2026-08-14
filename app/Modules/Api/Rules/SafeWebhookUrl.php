<?php

declare(strict_types=1);

namespace App\Modules\Api\Rules;

use App\Modules\Api\Support\WebhookUrlValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects webhook URLs that resolve anywhere an outbound request from this
 * server should never go.
 */
class SafeWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('The webhook URL must be a string.'));

            return;
        }

        $reason = WebhookUrlValidator::reject($value);

        if ($reason !== null) {
            $fail($reason);
        }
    }
}
