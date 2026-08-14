<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Services\EmailTemplateResolver;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Prefer an editable lifecycle template when one is active; otherwise keep the
 * hardcoded MailMessage built by the caller.
 */
trait AppliesEmailTemplate
{
    /**
     * @param  array<string, scalar|null>  $replacements
     */
    protected function mailFromTemplate(string $key, array $replacements, MailMessage $fallback): MailMessage
    {
        $resolved = app(EmailTemplateResolver::class)->resolve($key, $replacements);

        if ($resolved === null) {
            return $fallback;
        }

        $message = (new MailMessage)->subject($resolved['subject']);

        foreach (preg_split('/\R/u', $resolved['body']) ?: [] as $line) {
            $message->line($line);
        }

        return $message;
    }
}
