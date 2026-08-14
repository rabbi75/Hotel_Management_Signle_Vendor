<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class InvoicePaidNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}

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
            ->subject(__('Receipt for invoice :number', ['number' => $this->invoice->number]))
            ->line(__('Thank you — we have received :amount.', ['amount' => $this->invoice->totalMoney()->format()]))
            ->action(__('Download invoice'), route('billing.invoices.show', $this->invoice));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Invoice paid'),
            'body' => __(':number — :amount', [
                'number' => $this->invoice->number,
                'amount' => $this->invoice->totalMoney()->format(),
            ]),
            'icon' => 'receipt',
            'level' => 'success',
            'action_url' => route('billing.invoices.show', $this->invoice),
            'action_label' => __('View invoice'),
            'invoice_id' => $this->invoice->id,
        ];
    }
}
