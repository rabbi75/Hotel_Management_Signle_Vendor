<?php

declare(strict_types=1);

namespace App\Modules\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The admin console's own password-reset mail.
 *
 * Points at `/admin/reset-password`, not the tenant reset screen, so the link an
 * operator receives never lands them in the tenant app.
 */
class AdminPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('Reset your admin password'))
            ->line(__('You are receiving this email because we received a password reset request for your admin account.'))
            ->action(__('Reset password'), url($url))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
