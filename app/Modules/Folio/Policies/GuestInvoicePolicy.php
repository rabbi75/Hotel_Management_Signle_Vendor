<?php

declare(strict_types=1);

namespace App\Modules\Folio\Policies;

use App\Modules\Folio\Models\GuestInvoice;
use App\Modules\User\Models\User;

class GuestInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('guest_invoices.view');
    }

    public function view(User $user, GuestInvoice $invoice): bool
    {
        return $this->inCurrentWorkspace($invoice) && $user->can('guest_invoices.view');
    }

    public function download(User $user, GuestInvoice $invoice): bool
    {
        return $this->inCurrentWorkspace($invoice) && $user->can('guest_invoices.download');
    }

    protected function inCurrentWorkspace(GuestInvoice $invoice): bool
    {
        return $invoice->company_id === current_company_id();
    }
}
