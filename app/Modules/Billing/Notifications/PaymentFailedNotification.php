<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Platform\Support\AppliesEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use AppliesEmailTemplate;
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $attempt,
        public readonly int $graceDays,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fallback = (new MailMessage)
            ->error()
            ->subject(__('We could not take payment for your subscription'))
            ->line(__('Attempt :attempt to renew your :plan subscription was declined.', [
                'attempt' => $this->attempt,
                'plan' => $this->subscription->plan->name,
            ]))
            ->line(__('Your workspace keeps working for :days more day(s). Update your payment method to avoid interruption.', [
                'days' => $this->graceDays,
            ]));

        return $this->mailFromTemplate('payment_failed', [
            'app_name' => (string) config('saas.brand.name'),
            'user_name' => $notifiable->first_name ?? $notifiable->name ?? '',
            'plan' => $this->subscription->plan->name,
        ], $fallback)
            ->error()
            ->action(__('Update payment method'), route('billing.payment-methods.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Payment failed'),
            'body' => __('We could not renew your :plan subscription.', ['plan' => $this->subscription->plan->name]),
            'icon' => 'circle-alert',
            'level' => 'critical',
            'action_url' => route('billing.payment-methods.index'),
            'action_label' => __('Update payment method'),
            'subscription_id' => $this->subscription->id,
        ];
    }
}
