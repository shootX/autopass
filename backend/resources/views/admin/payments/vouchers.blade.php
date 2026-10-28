@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header d-flex justify-content-between align-items-baseline">
                <div>{{ __('admin.voucher_uses') }}</div>
                <div class="btn-group">
                    <a href="{{ route('admin.vouchers.uses') }}" class="btn btn-sm {{ $used ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('admin.voucher_uses_used') }}</a>
                    <a href="{{ route('admin.vouchers.uses', ['state' => 'active']) }}" class="btn btn-sm {{ $used ? 'btn-outline-primary' : 'btn-primary' }}">{{ __('admin.voucher_uses_active') }}</a>
                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead><th>{{ __('admin.voucher_uses_code') }}</th><th>{{ __('admin.vouchers') }}</th><th>{{ __('admin.client') }}</th><th>{{ __('admin.voucher_uses_bought') }}</th><th>{{ __('admin.voucher_uses_when') }}</th><th>{{ __('admin.partners') }}</th></thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row->code }}</strong></td>
                            <td>{{ $row->voucher?->name ?? '—' }}</td>
                            <td>@if($row->user)<a href="{{ route('admin.clients.show', $row->user) }}">{{ $row->user->titleName() }}</a>@else — @endif</td>
                            <td>{{ $row->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $row->used_at ? \Carbon\Carbon::parse($row->used_at)->format('d.m.Y H:i') : '—' }}</td>
                            <td>{{ $row->partner?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $rows->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
