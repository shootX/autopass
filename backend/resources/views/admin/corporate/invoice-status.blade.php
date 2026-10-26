@php($invoiceBadge = ['issued' => 'bg-warning text-dark', 'paid' => 'bg-success', 'cancelled' => 'bg-secondary'])
<span class="badge {{ $invoiceBadge[$invoice->status] ?? 'bg-secondary' }}">{{ __('admin.inv_status_'.$invoice->status) }}</span>
@if($invoice->paid_at)
    <small class="d-block text-muted">{{ $invoice->paid_at->format('d.m.Y') }}</small>
@endif
