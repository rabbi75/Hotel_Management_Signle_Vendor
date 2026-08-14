<?php

declare(strict_types=1);

namespace App\Modules\Folio\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Folio\Enums\GuestInvoiceStatus;
use App\Modules\Folio\Http\Resources\GuestInvoiceResource;
use App\Modules\Folio\Models\GuestInvoice;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\TableBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuestInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', GuestInvoice::class);

        $query = GuestInvoice::query()->with(['guest', 'reservation', 'folio']);

        $table = TableBuilder::for($query, $request, 'guest-invoices')
            ->columns([
                Column::make('number', __('Invoice'))->sortable()->searchable()->locked(),
                Column::make('guest', __('Guest'))->sortable('guest_id'),
                Column::make('reservation', __('Reservation'))->sortable('reservation_id'),
                Column::make('status', __('Status'))->sortable(),
                Column::make('total', __('Total'))->sortable()->align('right'),
                Column::make('issued_at', __('Issued'))->sortable(),
            ])
            ->filters([
                Filter::make('status', __('Status'))->fromEnum(GuestInvoiceStatus::class)->multiple(),
                Filter::make('issued_at', __('Issued'))->dateRange(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->transform(fn (GuestInvoice $invoice): array => (new GuestInvoiceResource($invoice))->resolve($request));

        return Inertia::render('guest-invoices/index', [
            'table' => $table->toArray(),
            'can' => [
                'download' => $request->user()?->can('guest_invoices.download') ?? false,
            ],
        ]);
    }

    public function show(GuestInvoice $guestInvoice): HttpResponse
    {
        Gate::authorize('view', $guestInvoice);

        return response()->view('folio::guest-invoice', $this->viewData($guestInvoice));
    }

    public function download(GuestInvoice $guestInvoice): HttpResponse
    {
        Gate::authorize('download', $guestInvoice);

        return response()
            ->view('folio::guest-invoice', $this->viewData($guestInvoice))
            ->header('Content-Disposition', 'attachment; filename="'.$guestInvoice->number.'.html"');
    }

    /** @return array<string, mixed> */
    protected function viewData(GuestInvoice $invoice): array
    {
        $invoice->load(['lines', 'guest', 'reservation', 'folio.hotel', 'company']);

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
