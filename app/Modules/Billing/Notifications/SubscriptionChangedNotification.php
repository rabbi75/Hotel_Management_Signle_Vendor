<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class SubscriptionChangedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly Plan $previousPlan,
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
        return (new MailMessage)
            ->subject(__('Your plan has changed'))
            ->line(__('Your workspace moved from :from to :to, billed :interval.', [
                'from' => $this->previousPlan->name,
                'to' => $this->subscription->plan->name,
                'interval' => $this->subscription->interval->label(),
            ]))
            ->line(__('Any difference for the remainder of the current period appears on your next invoice.'))
            ->action(__('View subscription'), route('billing.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Plan changed'),
            'body' => __(':from → :to', ['from' => $this->previousPlan->name, 'to' => $this->subscription->plan->name]),
            'icon' => 'credit-card',
            'level' => 'info',
            'action_url' => route('billing.index'),
            'action_label' => __('View subscription'),
            'subscription_id' => $this->subscription->id,
        ];
    }
}
