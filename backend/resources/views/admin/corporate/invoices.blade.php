{{-- A company's invoices with the order (company) or issue (admin) form. $manage is the admin. --}}
<div id="invoices"></div>
@error('invoice')
    <div class="alert alert-danger mt-4 mb-0">{{ $message }}</div>
@enderror
<div class="card mt-4 mb-4">
    <div class="card-header">{{ __('admin.inv_title') }}</div>
    <div class="card-body table-responsive">
        @if($orderable->isNotEmpty())
            <form class="row g-2 align-items-end" method="post" action="{{ $manage ? route('admin.corporate.invoices.store', $corporate) : route('partner.invoices.store') }}">
                @csrf
                <div class="col-md-6">
                    <label class="form-label" for="inv_package">{{ __('admin.inv_package') }}</label>
                    <select class="form-select" id="inv_package" name="corporate_package_id" required>
                        @foreach($orderable as $package)
                            <option value="{{ $package->id }}">{{ $package->title }} · {{ __('admin.cp_washes_count', ['count' => $package->washes]) }}@if($package->car_type) · {{ \App\Models\BodyType::labelFor($package->car_type) }}@endif · {{ number_format((float) $package->price, 2) }} ₾</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">{{ __($manage ? 'admin.inv_issue' : 'admin.inv_order') }}</button></div>
            </form>
            <small class="d-block mt-1 mb-3">{{ __($manage ? 'admin.inv_issue_hint' : 'admin.inv_order_hint') }}</small>
        @elseif(! $manage)
            <small class="d-block mb-3">{{ __('admin.inv_order_none') }}</small>
        @endif
        <small class="d-block mb-3 text-muted">{{ __('admin.inv_discount_hint') }}</small>
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>{{ __('admin.inv_number') }}</th>
                <th>{{ __('admin.inv_date') }}</th>
                <th>{{ __('admin.inv_package') }}</th>
                <th>{{ __('admin.inv_amount') }}</th>
                <th>{{ __('admin.inv_due') }}</th>
                <th>{{ __('admin.status') }}</th>
                <th>{{ __('admin.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->number }}</td>
                    <td>{{ $invoice->created_at?->format('d.m.Y') }}</td>
                    <td>{{ __('admin.inv_doc_washes', ['title' => $invoice->displayTitle(), 'count' => $invoice->washes]) }}</td>
                    <td>{{ number_format((float) $invoice->amount, 2) }} ₾</td>
                    <td>{{ $invoice->due_date?->format('d.m.Y') ?? '—' }}</td>
                    <td>@include('admin.corporate.invoice-status')</td>
                    <td class="text-nowrap">@include('admin.corporate.invoice-actions')</td>
                </tr>
            @empty
                <tr><td colspan="7" class="umami-empty">{{ __('admin.inv_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $invoices->links('pagination::bootstrap-5') }}
    </div>
</div>
