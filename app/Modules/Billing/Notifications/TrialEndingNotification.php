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

class TrialEndingNotification extends Notification implements ShouldQueue
{
    use AppliesEmailTemplate;
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $daysRemaining,
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
            ->subject(__('Your trial ends in :days day(s)', ['days' => $this->daysRemaining]))
            ->line(__('Your free trial of the :plan plan ends in :days day(s).', [
                'plan' => $this->subscription->plan->name,
                'days' => $this->daysRemaining,
            ]))
            ->line(__('Add a payment method now and nothing about your workspace changes.'));

        return $this->mailFromTemplate('trial_ending', [
            'app_name' => (string) config('saas.brand.name'),
            'user_name' => $notifiable->first_name ?? $notifiable->name ?? '',
            'plan' => $this->subscription->plan->name,
            'days' => $this->daysRemaining,
        ], $fallback)->action(__('Add a payment method'), route('billing.payment-methods.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Trial ending soon'),
            'body' => __(':days day(s) left on your :plan trial.', [
                'days' => $this->daysRemaining,
                'plan' => $this->subscription->plan->name,
            ]),
            'icon' => 'calendar-days',
            'level' => 'warning',
            'action_url' => route('billing.payment-methods.index'),
            'action_label' => __('Add a payment method'),
            'subscription_id' => $this->subscription->id,
        ];
    }
}
