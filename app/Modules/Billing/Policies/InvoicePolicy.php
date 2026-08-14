<?php

declare(strict_types=1);

namespace App\Modules\Billing\Policies;

use App\Modules\Billing\Models\Invoice;
use App\Modules\User\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->inCurrentWorkspace($invoice) && $user->can('billing.view');
    }

    public function download(User $user, Invoice $invoice): bool
    {
        return $this->inCurrentWorkspace($invoice) && $user->can('billing.invoices.download');
    }

    protected function inCurrentWorkspace(Invoice $invoice): bool
    {
        return $invoice->company_id === current_company_id();
    }
}
