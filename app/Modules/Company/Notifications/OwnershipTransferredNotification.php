<?php

declare(strict_types=1);

namespace App\Modules\Company\Notifications;

use App\Modules\Company\Models\Company;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to both parties of an ownership transfer.
 *
 * The copy is written from each recipient's point of view so the outgoing owner
 * is told what they lost and the incoming owner what they gained.
 */
class OwnershipTransferredNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly User $previousOwner,
        public readonly User $newOwner,
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
            ->subject(__('Ownership of :company has changed', ['company' => $this->company->name]))
            ->line($this->body($notifiable))
            ->line(__('If you did not expect this change, contact your workspace administrator immediately.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Workspace ownership transferred'),
            'body' => $this->body($notifiable),
            'icon' => 'key-round',
            'level' => 'warning',
            'action_url' => null,
            'action_label' => null,
            'company_id' => $this->company->id,
        ];
    }

    protected function body(object $notifiable): string
    {
        $isNewOwner = $notifiable instanceof User && $notifiable->id === $this->newOwner->id;

        return $isNewOwner
            ? (string) __('You are now the owner of :company. :previous is now an administrator.', [
                'company' => $this->company->name,
                'previous' => $this->previousOwner->name,
            ])
            : (string) __('You transferred ownership of :company to :new. Your role is now administrator.', [
                'company' => $this->company->name,
                'new' => $this->newOwner->name,
            ]);
    }
}
