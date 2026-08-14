<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class SubscriptionStartedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Subscription $subscription) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;

        $message = (new MailMessage)
            ->subject(__('Your :plan subscription is active', ['plan' => $plan->name]))
            ->greeting(__('Thanks for subscribing!'))
            ->line(__('Your workspace is now on the :plan plan, billed :interval.', [
                'plan' => $plan->name,
                'interval' => $this->subscription->interval->label(),
            ]));

        if ($this->subscription->onTrial() && $this->subscription->trial_ends_at !== null) {
            $message->line(__('Your free trial runs until :date.', [
                'date' => $this->subscription->trial_ends_at->toFormattedDateString(),
            ]));
        }

        return $message->action(__('View subscription'), route('billing.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Subscription started'),
            'body' => __('Your workspace is now on the :plan plan.', ['plan' => $this->subscription->plan->name]),
            'icon' => 'credit-card',
            'level' => 'success',
            'action_url' => route('billing.index'),
            'action_label' => __('View subscription'),
            'subscription_id' => $this->subscription->id,
        ];
    }
}
