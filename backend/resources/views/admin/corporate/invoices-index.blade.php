@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @if(session()->has('message'))
            <div class="alert alert-success mt-4">{{ session()->get('message') }}</div>
        @endif
        @error('invoice')
            <div class="alert alert-danger mt-4 mb-0">{{ $message }}</div>
        @enderror

        <div class="card mt-4 mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>{{ __('admin.inv_title') }}</div>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="post" action="{{ route('admin.corporate.invoices.discounts') }}" onsubmit="return confirm(@json(__('admin.inv_discount_run_confirm')))">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('admin.inv_discount_run') }}</button>
                    </form>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.corporate') }}">{{ __('admin.corporate_clients') }}</a>
                </div>
            </div>
            <div class="card-body table-responsive">
                <ul class="nav nav-pills mb-3">
                    @foreach(['issued', 'paid', 'cancelled'] as $item)
                        <li class="nav-item"><a class="nav-link @if($status === $item) active @endif" href="{{ route('admin.corporate.invoices', ['status' => $item]) }}">{{ __('admin.inv_status_'.$item) }}</a></li>
                    @endforeach
                    <li class="nav-item"><a class="nav-link @if($status === null) active @endif" href="{{ route('admin.corporate.invoices', ['status' => 'all']) }}">{{ __('admin.inv_all') }}</a></li>
                </ul>
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('admin.inv_number') }}</th>
                        <th>{{ __('admin.inv_date') }}</th>
                        <th>{{ __('admin.cp_company') }}</th>
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
                            <td>
                                @if($invoice->client)
                                    <a href="{{ route('admin.corporate.show', $invoice->client) }}#invoices">{{ $invoice->client->name }}</a>
                                @else
                                    {{ $invoice->buyer['name'] ?? '—' }}
                                @endif
                                <small class="d-block text-muted">{{ __('admin.inv_by_'.$invoice->ordered_by) }}</small>
                            </td>
                            <td>{{ __('admin.inv_doc_washes', ['title' => $invoice->displayTitle(), 'count' => $invoice->washes]) }}</td>
                            <td>{{ number_format((float) $invoice->amount, 2) }} ₾</td>
                            <td>{{ $invoice->due_date?->format('d.m.Y') ?? '—' }}</td>
                            <td>@include('admin.corporate.invoice-status')</td>
                            <td class="text-nowrap">@include('admin.corporate.invoice-actions', ['manage' => true])</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="umami-empty">{{ __('admin.inv_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $invoices->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
