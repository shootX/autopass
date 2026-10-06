@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4 umami-dash">
        <div class="umami-metrics">
            <a class="umami-metric" href="{{ route('admin.clients') }}">
                <span class="umami-metric-label">{{ __('admin.clients') }}</span>
                <span class="umami-metric-value">{{ $stats['clients'] }}</span>
                <span class="umami-metric-hint">{{ __('admin.dash_clients_7d') }} {{ $stats['clients_week'] }} · {{ __('admin.dash_banned') }} {{ $stats['banned'] }}</span>
            </a>
            <a class="umami-metric" href="{{ route('admin.appointments') }}">
                <span class="umami-metric-label">{{ __('admin.appointments') }}</span>
                <span class="umami-metric-value">{{ $stats['appointments'] }}</span>
                <span class="umami-metric-hint">{{ __('admin.dash_today') }} {{ $stats['appointments_today'] }} · {{ __('admin.dash_pending') }} {{ $stats['appointments_pending'] }}</span>
            </a>
            <div class="umami-metric">
                <span class="umami-metric-label">{{ __('admin.dash_revenue') }}</span>
                <span class="umami-metric-value">{{ number_format($stats['revenue'], 0, '.', ' ') }} ₾</span>
                <span class="umami-metric-hint">{{ __('admin.dash_paid') }} {{ $stats['payments'] }}</span>
            </div>
            <a class="umami-metric" href="{{ route('admin.packages.index') }}">
                <span class="umami-metric-label">{{ __('admin.packages') }}</span>
                <span class="umami-metric-value">{{ $stats['packages_active'] }}</span>
                <span class="umami-metric-hint">{{ __('admin.dash_active') }} · {{ $stats['packages_total'] }}</span>
            </a>
        </div>

        <div class="umami-metrics umami-metrics-sub">
            <a class="umami-metric" href="{{ route('admin.managers') }}">
                <span class="umami-metric-label">{{ __('admin.managers') }}</span>
                <span class="umami-metric-value">{{ $stats['managers'] }}</span>
            </a>
            <a class="umami-metric" href="{{ route('admin.partners') }}">
                <span class="umami-metric-label">{{ __('admin.partners') }}</span>
                <span class="umami-metric-value">{{ $stats['partners'] }}</span>
            </a>
            <a class="umami-metric" href="{{ route('admin.washings') }}">
                <span class="umami-metric-label">{{ __('admin.washings') }}</span>
                <span class="umami-metric-value">{{ $stats['washes'] }}</span>
            </a>
            <a class="umami-metric" href="{{ route('admin.car_brands') }}">
                <span class="umami-metric-label">{{ __('admin.dash_cars') }}</span>
                <span class="umami-metric-value">{{ $stats['cars'] }}</span>
                <span class="umami-metric-hint">{{ __('admin.brands_models') }} {{ $stats['brands'] }}</span>
            </a>
            <a class="umami-metric" href="{{ route('admin.vouchers') }}">
                <span class="umami-metric-label">{{ __('admin.vouchers') }}</span>
                <span class="umami-metric-value">{{ $stats['vouchers_issued'] }}</span>
                <span class="umami-metric-hint">{{ $stats['vouchers'] }}</span>
            </a>
            <a class="umami-metric" href="{{ route('admin.tickets') }}">
                <span class="umami-metric-label">{{ __('admin.tickets') }}</span>
                <span class="umami-metric-value">{{ $stats['tickets_issued'] }}</span>
                <span class="umami-metric-hint">{{ $stats['tickets'] }}</span>
            </a>
            <div class="umami-metric">
                <span class="umami-metric-label">{{ __('admin.dash_reviews') }}</span>
                <span class="umami-metric-value">{{ $stats['reviews'] }}</span>
                <span class="umami-metric-hint">{{ __('admin.dash_avg') }} {{ $stats['reviews_avg'] ?: '—' }}</span>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{{ __('admin.dash_trend') }}</div>
            <div class="card-body">
                <div class="umami-legend">
                    <span><i class="umami-dot"></i>{{ __('admin.appointments') }}</span>
                    <span><i class="umami-dot is-muted"></i>{{ __('admin.clients') }}</span>
                </div>
                <div class="umami-bars">
                    @foreach($trend as $day)
                        <div class="umami-bar-col">
                            <div class="umami-bar-pair">
                                <div class="umami-bar" style="height: {{ max(2, round($day['appointments'] / $maxTrend * 100)) }}%"></div>
                                <div class="umami-bar is-muted" style="height: {{ max(2, round($day['clients'] / $maxTrend * 100)) }}%"></div>
                            </div>
                            <span class="umami-bar-label">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ __('admin.dash_by_wash') }}</div>
                    <div class="card-body">
                        @forelse($washes as $row)
                            @php $max = max(1, $washes->max('total')); @endphp
                            <div class="umami-split">
                                <span>{{ $row->label }}</span>
                                <span class="umami-track"><span style="width: {{ round($row->total / $max * 100) }}%"></span></span>
                                <b>{{ $row->total }}</b>
                            </div>
                        @empty
                            <div class="umami-empty">{{ __('admin.dash_empty') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ __('admin.dash_by_package') }}</div>
                    <div class="card-body">
                        @forelse($packages as $row)
                            @php $max = max(1, $packages->max('total')); @endphp
                            <div class="umami-split">
                                <span>{{ $row->label ?: '—' }}</span>
                                <span class="umami-track"><span style="width: {{ round($row->total / $max * 100) }}%"></span></span>
                                <b>{{ $row->total }}</b>
                            </div>
                        @empty
                            <div class="umami-empty">{{ __('admin.dash_empty') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">{{ __('admin.dash_recent') }}</div>
            <div class="card-body table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('admin.full_name') }}</th>
                        <th>{{ __('admin.washings') }}</th>
                        <th>{{ __('admin.date') }}</th>
                        <th>{{ __('admin.time') }}</th>
                        <th>{{ __('admin.status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recent as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ trim(($item->user->name ?? '').' '.($item->user->surname ?? '')) ?: '—' }}</td>
                            <td>{{ $item->washing->name ?? '—' }}</td>
                            <td>{{ $item->date_formatted }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->time)->format('H:i') }}</td>
                            <td>{{ $item->approved ? __('admin.dash_approved') : __('admin.dash_pending') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="umami-empty">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
