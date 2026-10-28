@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.partials.alerts')
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.payments') }} · {{ __('admin.payments_total', ['sum' => number_format($total, 2)]) }}</div>
            <div class="card-body table-responsive">
                <form method="get" class="row g-2 mb-3">
                    <div class="col-md-2">
                        <select name="type" class="form-select">
                            <option value="">{{ __('admin.payments_all_types') }}</option>
                            @foreach(['package', 'points'] as $type)
                                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __('admin.payments_type_'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">{{ __('admin.payments_all_statuses') }}</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
                    <div class="col-md-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
                    <div class="col-md-2 form-check mt-2 ms-2"><input type="checkbox" name="test" value="1" class="form-check-input" id="test" @checked($filters['test'] ?? false)><label for="test" class="form-check-label">{{ __('admin.payments_test') }}</label></div>
                    <div class="col-md-1"><button class="btn btn-primary">{{ __('admin.filter') }}</button></div>
                </form>
                <table class="table table-hover">
                    <thead><th>ID</th><th>{{ __('admin.date') }}</th><th>{{ __('admin.client') }}</th><th>{{ __('admin.payments_type') }}</th><th>{{ __('admin.payments_amount') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.payments_order') }}</th></thead>
                    <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>{{ $payment->created_at?->format('d.m.Y H:i') }}</td>
                            <td>@if($payment->user)<a href="{{ route('admin.clients.show', $payment->user) }}">{{ $payment->user->titleName() }}</a>@else — @endif</td>
                            <td>
                                {{ __('admin.payments_type_'.$payment->type) }}
                                @if($payment->type === 'points') · {{ (int) ($payment->payload['points'] ?? 0) }}@endif
                                @if(! empty($payment->payload['quote']['promo_code']))<span class="badge bg-info text-dark">{{ $payment->payload['quote']['promo_code'] }}</span>@endif
                            </td>
                            <td>{{ number_format((float) $payment->amount, 2) }} ₾</td>
                            <td><span class="badge {{ $payment->status === 'succeeded' ? 'bg-success' : 'bg-secondary' }}">{{ $payment->status }}</span></td>
                            <td class="text-muted small">{{ $payment->merchant_payment_id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $payments->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
