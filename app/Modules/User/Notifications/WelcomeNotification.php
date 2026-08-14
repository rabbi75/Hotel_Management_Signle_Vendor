<?php

declare(strict_types=1);

namespace App\Modules\User\Notifications;

use App\Modules\Company\Models\Company;
use App\Modules\Platform\Support\AppliesEmailTemplate;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an administrator provisions an account on someone's behalf.
 *
 * When the account was created without a password the recipient is sent through
 * the password-reset flow instead of being mailed a credential.
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use AppliesEmailTemplate;
    use Queueable;

    public function __construct(public readonly ?Company $company = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $brand = (string) config('saas.brand.name');
        $workspace = $this->company instanceof Company ? $this->company->name : $brand;

        // A generated password is never mailed; the recipient always proves
        // control of the address by going through the reset flow.
        $fallback = (new MailMessage)
            ->subject(__('Welcome to :workspace', ['workspace' => $workspace]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? $notifiable->name]))
            ->line(__('An account has been created for you on :workspace.', ['workspace' => $workspace]))
            ->line(__('Set your password to activate the account.'));

        return $this->mailFromTemplate('welcome', [
            'app_name' => $brand,
            'user_name' => $notifiable->first_name ?? $notifiable->name,
        ], $fallback)->action(__('Set your password'), route('password.request'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user.welcome',
            'title' => __('Welcome aboard'),
            'message' => __('Your account is ready.'),
            'company_id' => $this->company?->id,
        ];
    }
}
