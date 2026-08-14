<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $query = Invoice::query()->with('subscription.plan');

        $table = TableBuilder::for($query, $request, 'invoices')
            ->columns([
                Column::make('number', __('Invoice'))->sortable()->searchable()->locked(),
                Column::make('status', __('Status'))->sortable(),
                Column::make('total', __('Total'))->sortable()->align('right'),
                Column::make('issued_at', __('Issued'))->sortable(),
                Column::make('due_at', __('Due'))->sortable()->hidden(),
                Column::make('paid_at', __('Paid'))->sortable()->hidden(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(InvoiceStatus::class)->multiple(),
                Filter::make('issued_at', __('Issued'))->dateRange(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->transform(fn (Invoice $invoice): array => (new InvoiceResource($invoice))->resolve($request));

        return Inertia::render('billing/invoices', [
            'table' => $table->toArray(),
            'can' => [
                'download' => $request->user()?->can('billing.invoices.download') ?? false,
            ],
        ]);
    }

    /**
     * The printable invoice. Rendered as HTML rather than PDF so the kit ships
     * without binding itself to a PDF engine; the browser's own "print to PDF"
     * produces the same document.
     */
    public function show(Request $request, Invoice $invoice): HttpResponse
    {
        Gate::authorize('view', $invoice);

        return response()->view('billing::invoice', $this->viewData($invoice));
    }

    public function download(Request $request, Invoice $invoice): HttpResponse
    {
        Gate::authorize('download', $invoice);

        return response()
            ->view('billing::invoice', $this->viewData($invoice))
            ->header('Content-Disposition', 'attachment; filename="'.$invoice->number.'.html"');
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(Invoice $invoice): array
    {
        $invoice->load(['lines', 'subscription.plan', 'company']);

        return [
            'invoice' => $invoice,
            'brand' => [
                'name' => config('saas.billing.invoice.from_name') ?? config('saas.brand.name'),
                'vat_number' => config('saas.billing.invoice.vat_number'),
                'support_email' => config('saas.brand.support_email'),
            ],
        ];
    }
}
