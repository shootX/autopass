{{-- Print, and for an unpaid invoice: paid (admin only) and cancel. --}}
<a class="btn btn-sm btn-outline-secondary" href="{{ $manage ? route('admin.corporate.invoices.show', $invoice) : route('partner.invoices.show', $invoice) }}" target="_blank" rel="noopener" title="{{ __('admin.inv_view') }}"><i class="fas fa-print"></i></a>
@if($invoice->isOpen())
    @if($manage)
        <form method="post" action="{{ route('admin.corporate.invoices.paid', $invoice) }}" class="d-inline" onsubmit="return confirm(@json(__('admin.inv_paid_confirm', ['count' => $invoice->washes])))">@csrf<button class="btn btn-sm btn-success" type="submit">{{ __('admin.inv_paid_btn') }}</button></form>
    @endif
    <form method="post" action="{{ $manage ? route('admin.corporate.invoices.cancel', $invoice) : route('partner.invoices.cancel', $invoice) }}" class="d-inline" onsubmit="return confirm(@json(__('admin.inv_cancel_confirm')))">@csrf<button class="btn btn-sm btn-outline-danger" type="submit" title="{{ __('admin.inv_cancel') }}"><i class="fas fa-xmark"></i></button></form>
@endif
