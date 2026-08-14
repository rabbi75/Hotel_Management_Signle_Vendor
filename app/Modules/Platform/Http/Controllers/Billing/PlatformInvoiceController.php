<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Http\Controllers\Billing\Concerns\ManagesPlatformBilling;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every invoice the installation has issued, across every workspace.
 *
 * The tenant screen answers "what do I owe"; this one answers "who owes us",
 * which is why it carries a workspace column and no company filter of its own.
 * The printable document is deliberately the same Blade view the customer gets
 * — an operator looking at a different invoice from the one in the customer's
 * inbox is how billing disputes start.
 */
class PlatformInvoiceController extends Controller
{
    use ManagesPlatformBilling;

    public function index(Request $request): Response
    {
        $this->authorizeBillingRead($request);

        $query = $this->invoices()->with(['company', 'subscription.plan']);

        $table = TableBuilder::for($query, $request, 'invoices')
            ->columns([
                Column::make('number', __('Invoice'))->sortable()->searchable()->locked(),
                Column::make('company', __('Workspace'))->sortable('company_id'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('total', __('Total'))->sortable()->align('right'),
                Column::make('issued_at', __('Issued'))->sortable(),
                Column::make('due_at', __('Due'))->sortable()->hidden(),
                Column::make('paid_at', __('Paid'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(InvoiceStatus::class)->multiple(),
                Filter::make('company_id', __('Workspace'))->options($this->workspaceOptions())->multiple(),
                Filter::make('issued_at', __('Issued'))->dateRange(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->transform(fn (Invoice $invoice): array => (new InvoiceResource($invoice))->resolve($request));

        return Inertia::render('admin/billing/invoices', [
            'table' => $table->toArray(),
            'can' => [
                'refund' => $request->user('admin')?->can('platform.billing.manage') ?? false,
            ],
        ]);
    }

    /**
     * The printable invoice — the customer's document, rendered by the same
     * view and with the same data.
     */
    public function show(Request $request, Invoice $invoice): HttpResponse
    {
        $this->authorizeBillingRead($request);

        return response()->view('billing::invoice', $this->viewData($invoice));
    }

    public function download(Request $request, Invoice $invoice): HttpResponse
    {
        $this->authorizeBillingRead($request);

        return response()
            ->view('billing::invoice', $this->viewData($invoice))
            ->header('Content-Disposition', 'attachment; filename="'.$invoice->number.'.html"');
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(Invoice $invoice): array
    {
        $invoice->load([
            'lines',
            'subscription.plan',
            'company' => fn ($query) => $query->withoutGlobalScope(CompanyScope::class),
        ]);

        return [
            'invoice' => $invoice,
            'brand' => [
                'name' => config('saas.billing.invoice.from_name') ?? config('saas.brand.name'),
                'vat_number' => config('saas.billing.invoice.vat_number'),
                'support_email' => config('saas.brand.support_email'),
            ],
        ];
    }

    /**
     * @return array<array-key, string>
     */
    protected function workspaceOptions(): array
    {
        $options = [];

        foreach (Company::query()->orderBy('name')->get(['id', 'name']) as $company) {
            $options[(string) $company->id] = $company->name;
        }

        return $options;
    }
}
