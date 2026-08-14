<?php

declare(strict_types=1);

namespace App\Modules\Company\Notifications;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\CompanyInvitation;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class MemberInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CompanyInvitation $invitation,
        public readonly Company $company,
    ) {}

    /**
     * The database channel needs a notifiable with a table row behind it, so an
     * invitation to an address with no account yet is mail only.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->inviter()->first();

        return (new MailMessage)
            ->subject(__('You have been invited to join :company', ['company' => $this->company->name]))
            ->greeting(__('Hello!'))
            ->line(__(':inviter has invited you to join the :company workspace as :role.', [
                'inviter' => $inviter->name ?? __('A colleague'),
                'company' => $this->company->name,
                'role' => $this->invitation->role->label(),
            ]))
            ->action(__('Accept invitation'), $this->acceptUrl())
            ->line(__('This invitation expires on :date.', [
                'date' => $this->invitation->expires_at->toDayDateTimeString(),
            ]))
            ->line(__('If you were not expecting this invitation you can safely ignore this email.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Invitation to :company', ['company' => $this->company->name]),
            'body' => __('You have been invited to join as :role.', ['role' => $this->invitation->role->label()]),
            'icon' => 'mail-plus',
            'level' => 'info',
            'action_url' => $this->acceptUrl(),
            'action_label' => __('Accept invitation'),
            'company_id' => $this->company->id,
            'invitation_id' => $this->invitation->id,
        ];
    }

    /**
     * The token is the credential the acceptance flow checks; the signature is
     * additional tamper evidence on the emailed link, not the gate itself —
     * the link must survive a detour through registration.
     */
    protected function acceptUrl(): string
    {
        return URL::signedRoute('invitations.show', ['invitation' => $this->invitation->token]);
    }
}
