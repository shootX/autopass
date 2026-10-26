{{-- Every approved wash of the company's cars; a discount wash shows its price and what the driver paid on site. --}}
<div id="history"></div>
<div class="card mt-4 mb-4">
    <div class="card-header">{{ __('admin.ch_title') }}</div>
    <div class="card-body table-responsive">
        <div class="d-flex flex-wrap gap-4 mb-3">
            <div><div class="text-muted">{{ __('admin.ch_month_washes') }}</div><div class="fs-4 fw-bold">{{ $month['washes'] }}</div></div>
            <div><div class="text-muted">{{ __('admin.ch_month_discount') }}</div><div class="fs-4 fw-bold">{{ $month['discount'] }}</div></div>
            <div><div class="text-muted">{{ __('admin.ch_month_full') }}</div><div class="fs-4 fw-bold">{{ number_format($month['full'], 2) }} ₾</div></div>
            <div><div class="text-muted">{{ __('admin.ch_month_paid') }}</div><div class="fs-4 fw-bold">{{ number_format($month['paid'], 2) }} ₾</div></div>
        </div>
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ __('admin.cw_date') }}</th>
                <th>{{ __('admin.plate') }}</th>
                <th>{{ __('admin.body_type') }}</th>
                <th>{{ __('admin.ch_branch') }}</th>
                <th>{{ __('admin.cw_mode') }}</th>
                <th>{{ __('admin.cd_full_price') }}</th>
                <th>{{ __('admin.cd_percent') }}</th>
                <th>{{ __('admin.ch_paid') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($history as $wash)
                <tr>
                    <td>{{ $wash->created_at?->format('d.m.Y H:i') }}</td>
                    <td>{{ \App\Services\FleetCars::formatPlate($wash->plate) }}</td>
                    <td>{{ $wash->car_type ?? '—' }}</td>
                    <td>
                        {{ $wash->washing?->name ?? '—' }}
                        @if($wash->walk_in)<small class="d-block text-muted">{{ __('admin.ch_walk_in') }}</small>@endif
                    </td>
                    <td>{{ __('admin.cw_mode_'.$wash->mode) }}</td>
                    <td>{{ $wash->full_price !== null ? $wash->full_price.' ₾' : '—' }}</td>
                    <td>{{ $wash->discount_percent !== null ? $wash->discount_percent.'%' : '—' }}</td>
                    <td>{{ $wash->to_pay !== null ? $wash->to_pay.' ₾' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="umami-empty">{{ __('admin.ch_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $history->fragment('history')->links('pagination::bootstrap-5') }}
    </div>
</div>
