<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\EmailTemplate;

/**
 * Resolves editable lifecycle email copy with hardcoded fallbacks.
 */
class EmailTemplateResolver
{
    /**
     * @param  array<string, scalar|null>  $replacements
     * @return array{subject: string, body: string}|null
     */
    public function resolve(string $key, array $replacements = []): ?array
    {
        $template = EmailTemplate::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $template instanceof EmailTemplate) {
            return null;
        }

        return [
            'subject' => $template->renderSubject($replacements),
            'body' => $template->renderBody($replacements),
        ];
    }

    /**
     * @return list<array{key: string, name: string, subject: string, body: string, placeholders: list<string>}>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => 'welcome',
                'name' => 'Welcome',
                'subject' => 'Welcome to {{app_name}}',
                'body' => "Hi {{user_name}},\n\nYour account is ready. Sign in to get started with {{app_name}}.\n\nThanks,\nThe {{app_name}} team",
                'placeholders' => ['app_name', 'user_name'],
            ],
            [
                'key' => 'trial_ending',
                'name' => 'Trial ending',
                'subject' => 'Your trial ends in {{days}} day(s)',
                'body' => "Hi {{user_name}},\n\nYour free trial of the {{plan}} plan ends in {{days}} day(s).\n\nAdd a payment method now and nothing about your workspace changes.\n\nThanks,\nThe {{app_name}} team",
                'placeholders' => ['app_name', 'user_name', 'plan', 'days'],
            ],
            [
                'key' => 'payment_failed',
                'name' => 'Payment failed',
                'subject' => 'Payment failed for {{app_name}}',
                'body' => "Hi {{user_name}},\n\nWe could not collect payment for your {{plan}} subscription. Please update your payment method to avoid interruption.\n\nThanks,\nThe {{app_name}} team",
                'placeholders' => ['app_name', 'user_name', 'plan'],
            ],
            [
                'key' => 'subscription_cancelled',
                'name' => 'Subscription cancelled',
                'subject' => 'Your {{plan}} subscription was cancelled',
                'body' => "Hi {{user_name}},\n\nYour {{plan}} subscription has been cancelled. You can resubscribe any time from billing settings.\n\nThanks,\nThe {{app_name}} team",
                'placeholders' => ['app_name', 'user_name', 'plan'],
            ],
            [
                'key' => 'tenant_suspended',
                'name' => 'Tenant suspended',
                'subject' => 'Access to {{workspace}} is suspended',
                'body' => "Hi {{user_name}},\n\nAccess to the workspace {{workspace}} has been suspended. Contact support if you believe this is a mistake.\n\nThanks,\nThe {{app_name}} team",
                'placeholders' => ['app_name', 'user_name', 'workspace'],
            ],
        ];
    }

    public function seedDefaults(): void
    {
        foreach (self::defaults() as $definition) {
            EmailTemplate::query()->firstOrCreate(
                ['key' => $definition['key']],
                [
                    'name' => $definition['name'],
                    'subject' => $definition['subject'],
                    'body' => $definition['body'],
                    'placeholders' => $definition['placeholders'],
                    'is_active' => true,
                ],
            );
        }
    }
}
