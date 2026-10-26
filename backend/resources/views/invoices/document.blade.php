@php
    $seller = $invoice->seller ?? [];
    $buyer = $invoice->buyer ?? [];
    $amount = number_format((float) $invoice->amount, 2, '.', ' ');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('admin.inv_doc_title') }} {{ $invoice->number }}</title>
    <style>
        :root { --ink: #1d2420; --muted: #5d6862; --line: #dde2de; --accent: #1f6f5c; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f2f4f2; color: var(--ink); font: 14px/1.5 "Noto Sans Georgian", "DejaVu Sans", system-ui, sans-serif; }
        .sheet { max-width: 800px; margin: 24px auto; background: #fff; padding: 40px 44px; border: 1px solid var(--line); }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 26px; letter-spacing: .02em; }
        .meta { color: var(--muted); }
        .status { display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid currentColor; }
        .status.issued { color: #a35d00; } .status.paid { color: #2e7d32; } .status.cancelled { color: var(--muted); }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 32px 0; }
        .party h2 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: var(--accent); }
        .party div { overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { font-size: 12px; color: var(--muted); font-weight: 600; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .totals { margin-left: auto; margin-top: 12px; width: 320px; max-width: 100%; }
        .totals td { border: 0; padding: 4px 8px; }
        .totals .grand td { font-size: 18px; font-weight: 700; border-top: 2px solid var(--ink); padding-top: 8px; }
        .note { margin-top: 28px; padding: 12px 14px; background: #f6f7f5; border-left: 3px solid var(--accent); }
        .actions { max-width: 800px; margin: 0 auto 24px; text-align: right; }
        .actions button { font: inherit; padding: 8px 16px; border: 0; border-radius: 6px; background: var(--accent); color: #fff; cursor: pointer; }
        @media (max-width: 600px) { .sheet { padding: 24px 16px; margin: 0; border: 0; } .parties { grid-template-columns: 1fr; } .actions { padding: 16px; } }
        @media print { body { background: #fff; } .sheet { margin: 0; border: 0; padding: 0; max-width: none; } .actions { display: none; } }
    </style>
</head>
<body>
<main class="sheet">
    <div class="top">
        <div>
            <h1>{{ __('admin.inv_doc_title') }} {{ $invoice->number }}</h1>
            <div class="meta">{{ __('admin.inv_date') }}: {{ $invoice->created_at?->format('d.m.Y') }}</div>
            @if($invoice->due_date)
                <div class="meta">{{ __('admin.inv_due') }}: {{ $invoice->due_date->format('d.m.Y') }}</div>
            @endif
            <span class="status {{ $invoice->status }}">{{ __('admin.inv_status_'.$invoice->status) }}@if($invoice->paid_at) · {{ $invoice->paid_at->format('d.m.Y') }}@endif</span>
        </div>
        <div style="text-align:right">
            <div style="font-weight:700;font-size:18px">{{ $seller['name'] ?? '' }}</div>
            @if(! empty($seller['email']))<div class="meta">{{ $seller['email'] }}</div>@endif
            @if(! empty($seller['phone']))<div class="meta">{{ $seller['phone'] }}</div>@endif
        </div>
    </div>

    <div class="parties">
        @foreach(['inv_doc_seller' => $seller, 'inv_doc_buyer' => $buyer] as $label => $party)
            <section class="party">
                <h2>{{ __('admin.'.$label) }}</h2>
                <div style="font-weight:600">{{ trim(($party['legal_form'] ?? '').' '.($party['name'] ?? '')) }}</div>
                @if(! empty($party['identification_code']))<div>{{ __('admin.inv_doc_code') }}: {{ $party['identification_code'] }}</div>@endif
                @if(! empty($party['legal_address'] ?? $party['address'] ?? null))<div>{{ __('admin.inv_doc_address') }}: {{ $party['legal_address'] ?? $party['address'] }}</div>@endif
                @if(! empty($party['bank_name']))<div>{{ __('admin.inv_doc_bank') }}: {{ $party['bank_name'] }}@if(! empty($party['bank_code'])) ({{ $party['bank_code'] }})@endif</div>@endif
                @if(! empty($party['bank_account']))<div>{{ __('admin.inv_doc_account') }}: {{ $party['bank_account'] }}</div>@endif
                @if(array_key_exists('vat_payer', $party))<div>{{ __('admin.inv_doc_vat_payer') }}: {{ $party['vat_payer'] ? __('admin.yes') : __('admin.no') }}</div>@endif
            </section>
        @endforeach
    </div>

    <table>
        <thead>
        <tr>
            <th>{{ __('admin.inv_doc_item') }}</th>
            <th class="num">{{ __('admin.inv_doc_qty') }}</th>
            <th class="num">{{ __('admin.inv_amount') }}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $invoice->title }}</td>
            <td class="num">{{ __('admin.cp_washes_count', ['count' => $invoice->washes]) }}</td>
            <td class="num">{{ $amount }} ₾</td>
        </tr>
        </tbody>
    </table>

    <table class="totals">
        @if($invoice->vat_rate > 0)
            <tr><td>{{ __('admin.inv_doc_vat', ['rate' => $invoice->vat_rate]) }}</td><td class="num">{{ number_format((float) $invoice->vatAmount(), 2, '.', ' ') }} ₾</td></tr>
        @else
            <tr><td colspan="2" class="meta">{{ __('admin.inv_doc_no_vat') }}</td></tr>
        @endif
        <tr class="grand"><td>{{ __('admin.inv_doc_total') }}</td><td class="num">{{ $amount }} ₾</td></tr>
    </table>

    <p class="note">{{ __('admin.inv_doc_purpose', ['number' => $invoice->number]) }}</p>
</main>
<div class="actions"><button type="button" onclick="window.print()">{{ __('admin.inv_doc_print') }}</button></div>
</body>
</html>
