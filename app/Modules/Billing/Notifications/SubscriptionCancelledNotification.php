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

class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use AppliesEmailTemplate;
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
        $endsAt = $this->subscription->cancels_at ?? $this->subscription->ended_at;

        $fallback = (new MailMessage)
            ->subject(__('Your subscription has been cancelled'))
            ->line($endsAt !== null
                ? __('Your :plan subscription will end on :date. You keep full access until then.', [
                    'plan' => $this->subscription->plan->name,
                    'date' => $endsAt->toFormattedDateString(),
                ])
                : __('Your :plan subscription has ended.', ['plan' => $this->subscription->plan->name]))
            ->line(__('Changed your mind? You can resume at any time before the period ends.'));

        return $this->mailFromTemplate('subscription_cancelled', [
            'app_name' => (string) config('saas.brand.name'),
            'user_name' => $notifiable->first_name ?? $notifiable->name ?? '',
            'plan' => $this->subscription->plan->name,
        ], $fallback)->action(__('Manage subscription'), route('billing.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Subscription cancelled'),
            'body' => __('Your :plan subscription will not renew.', ['plan' => $this->subscription->plan->name]),
            'icon' => 'credit-card',
            'level' => 'warning',
            'action_url' => route('billing.index'),
            'action_label' => __('Manage subscription'),
            'subscription_id' => $this->subscription->id,
        ];
    }
}
