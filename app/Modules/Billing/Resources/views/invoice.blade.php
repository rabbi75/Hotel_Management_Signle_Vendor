{{--
    The printable invoice.

    Rendered as standalone HTML rather than a PDF so the kit does not bind
    itself to a PDF engine; the browser's own "print to PDF" produces the same
    document, and the styles below are inlined so the file stays valid when it
    is saved or emailed away from the application.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice :number', ['number' => $invoice->number]) }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 2.5rem 1.5rem;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.55;
            color: #1c1c22;
            background: #f6f6f8;
        }
        .sheet { max-width: 52rem; margin: 0 auto; background: #fff; border-radius: 12px; padding: 2.5rem; }
        header { display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: space-between; align-items: flex-start; }
        h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
        .muted { color: #6b6b76; }
        .status { display: inline-block; padding: .15rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; border: 1px solid currentColor; }
        .grid { display: flex; flex-wrap: wrap; gap: 2rem; margin: 2rem 0; }
        .grid > div { min-width: 12rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: .65rem .5rem; border-bottom: 1px solid #e6e6ea; }
        th { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6b6b76; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        tfoot td { border-bottom: none; padding-top: .4rem; }
        tfoot tr:last-child td { font-weight: 700; font-size: 1.05rem; border-top: 2px solid #1c1c22; padding-top: .8rem; }
        footer { margin-top: 2.5rem; font-size: .8rem; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <header>
        <div>
            <h1>{{ $brand['name'] }}</h1>
            @if ($brand['vat_number'])
                <p class="muted">{{ __('VAT') }}: {{ $brand['vat_number'] }}</p>
            @endif
        </div>
        <div style="text-align: right">
            <h1>{{ __('Invoice') }}</h1>
            <p class="muted">{{ $invoice->number }}</p>
            <p><span class="status">{{ $invoice->status->label() }}</span></p>
        </div>
    </header>

    <div class="grid">
        <div>
            <p class="muted">{{ __('Billed to') }}</p>
            <p><strong>{{ $invoice->company->name }}</strong></p>
            @if ($invoice->company->email)<p class="muted">{{ $invoice->company->email }}</p>@endif
            @if ($invoice->company->tax_id)<p class="muted">{{ __('Tax ID') }}: {{ $invoice->company->tax_id }}</p>@endif
        </div>
        <div>
            <p class="muted">{{ __('Issued') }}</p>
            <p>{{ $invoice->issued_at?->toFormattedDateString() ?? '—' }}</p>
        </div>
        <div>
            <p class="muted">{{ __('Due') }}</p>
            <p>{{ $invoice->due_at?->toFormattedDateString() ?? '—' }}</p>
        </div>
        @if ($invoice->paid_at)
            <div>
                <p class="muted">{{ __('Paid') }}</p>
                <p>{{ $invoice->paid_at->toFormattedDateString() }}</p>
            </div>
        @endif
    </div>

    <table>
        <caption class="muted" style="text-align:left; padding-bottom:.5rem">{{ __('Items on this invoice') }}</caption>
        <thead>
        <tr>
            <th scope="col">{{ __('Description') }}</th>
            <th scope="col">{{ __('Period') }}</th>
            <th scope="col" class="num">{{ __('Qty') }}</th>
            <th scope="col" class="num">{{ __('Unit') }}</th>
            <th scope="col" class="num">{{ __('Amount') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="muted">{{ $line->period ?? '—' }}</td>
                <td class="num">{{ $line->quantity }}</td>
                <td class="num">{{ \App\Modules\Billing\Support\Money::of($line->unit_amount, $invoice->currency)->format() }}</td>
                <td class="num">{{ \App\Modules\Billing\Support\Money::of($line->amount, $invoice->currency)->format() }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="4" class="num muted">{{ __('Subtotal') }}</td>
            <td class="num">{{ $invoice->subtotalMoney()->format() }}</td>
        </tr>
        @if ($invoice->discount > 0)
            <tr>
                <td colspan="4" class="num muted">{{ __('Discount') }}</td>
                <td class="num">−{{ $invoice->discountMoney()->format() }}</td>
            </tr>
        @endif
        @if ($invoice->tax > 0)
            <tr>
                <td colspan="4" class="num muted">{{ __('Tax') }}</td>
                <td class="num">{{ $invoice->taxMoney()->format() }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="4" class="num">{{ __('Total') }}</td>
            <td class="num">{{ $invoice->totalMoney()->format() }}</td>
        </tr>
        </tfoot>
    </table>

    <footer class="muted">
        @if ($invoice->notes)<p>{{ $invoice->notes }}</p>@endif
        <p>{{ __('Questions about this invoice? Contact :email.', ['email' => $brand['support_email']]) }}</p>
    </footer>
</div>
</body>
</html>
