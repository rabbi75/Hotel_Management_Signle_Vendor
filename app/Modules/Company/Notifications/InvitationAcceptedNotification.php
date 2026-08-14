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
use Illuminate\Support\Facades\Route;

/**
 * Tells the inviter that their invitation was taken up.
 */
class InvitationAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
        public readonly User $member,
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
            ->subject(__(':name joined :company', ['name' => $this->member->name, 'company' => $this->company->name]))
            ->line(__(':name (:email) has accepted your invitation and joined :company.', [
                'name' => $this->member->name,
                'email' => $this->member->email,
                'company' => $this->company->name,
            ]))
            ->when($this->membersUrl() !== null, fn (MailMessage $mail): MailMessage => $mail->action(
                __('View members'),
                (string) $this->membersUrl(),
            ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Invitation accepted'),
            'body' => __(':name joined :company.', ['name' => $this->member->name, 'company' => $this->company->name]),
            'icon' => 'user-check',
            'level' => 'success',
            'action_url' => $this->membersUrl(),
            'action_label' => $this->membersUrl() === null ? null : __('View members'),
            'company_id' => $this->company->id,
        ];
    }

    protected function membersUrl(): ?string
    {
        return Route::has('companies.members.index') ? route('companies.members.index') : null;
    }
}
