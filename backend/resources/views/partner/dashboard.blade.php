@extends('partner.layout')

@section('content')
    <div class="container-fluid px-4 umami-dash">
        <div class="umami-metrics">
            <a class="umami-metric" href="{{ route('partner.fleet') }}">
                <span class="umami-metric-label">{{ __('admin.fleet') }}</span>
                <span class="umami-metric-value">{{ $stats['cars'] }}</span>
            </a>
            <a class="umami-metric" href="{{ route('partner.reports') }}">
                <span class="umami-metric-label">{{ __('admin.wash_report') }}</span>
                <span class="umami-metric-value">{{ $stats['washes'] }}</span>
                <span class="umami-metric-hint">{{ __('admin.dash_today') }} {{ $stats['today'] }}</span>
            </a>
            <div class="umami-metric">
                <span class="umami-metric-label">{{ __('admin.dash_today') }}</span>
                <span class="umami-metric-value">{{ $stats['today'] }}</span>
            </div>
            <div class="umami-metric">
                <span class="umami-metric-label">{{ __('admin.dash_pending') }}</span>
                <span class="umami-metric-value">{{ $stats['pending'] }}</span>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{{ __('admin.dash_trend') }}</div>
            <div class="card-body">
                <div class="umami-legend">
                    <span><i class="umami-dot"></i>{{ __('admin.appointments') }}</span>
                </div>
                <div class="umami-bars">
                    @foreach($trend as $day)
                        <div class="umami-bar-col">
                            <div class="umami-bar-pair">
                                <div class="umami-bar" style="height: {{ max(2, round($day['total'] / $maxTrend * 100)) }}%"></div>
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
                        @forelse($byWash as $row)
                            @php $max = max(1, $byWash->max('total')); @endphp
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
                    <div class="card-header">{{ __('admin.fleet') }}</div>
                    <div class="card-body">
                        @forelse($byPlate as $row)
                            @php $max = max(1, $byPlate->max('total')); @endphp
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
        </div>

        <div class="card mb-4">
            <div class="card-header">{{ __('admin.dash_recent') }}</div>
            <div class="card-body table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('admin.plate') }}</th>
                        <th>{{ __('admin.car_wash') }}</th>
                        <th>{{ __('admin.date') }}</th>
                        <th>{{ __('admin.time') }}</th>
                        <th>{{ __('admin.status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recent as $item)
                        <tr>
                            <td>{{ $item->car->plate ?? '—' }}</td>
                            <td>{{ $item->washing->name ?? '—' }}</td>
                            <td>{{ $item->date_formatted }}</td>
                            <td>{{ \Carbon\Carbon::parse($item->time)->format('H:i') }}</td>
                            <td>{{ \App\Services\WashReportQuery::statusLabel($item->approved) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="umami-empty">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
