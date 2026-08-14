<?php

declare(strict_types=1);

namespace App\Modules\Company\Notifications;

use App\Modules\Company\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class RemovedFromWorkspaceNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Company $company) {}

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
            ->subject(__('You have been removed from :company', ['company' => $this->company->name]))
            ->line(__('Your access to the :company workspace has been removed.', ['company' => $this->company->name]))
            ->line(__('Your account and any other workspaces you belong to are unaffected.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Removed from :company', ['company' => $this->company->name]),
            'body' => __('You no longer have access to this workspace.'),
            'icon' => 'user-minus',
            'level' => 'warning',
            'action_url' => null,
            'action_label' => null,
            'company_id' => $this->company->id,
        ];
    }
}
