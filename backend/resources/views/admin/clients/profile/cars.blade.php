<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.brand') }}</th><th>{{ __('admin.model') }}</th><th>{{ __('admin.p_trim') }}</th><th>{{ __('admin.p_year') }}</th><th>{{ __('admin.p_fuel') }}</th><th>{{ __('admin.plate') }}</th><th>{{ __('admin.p_tab_washes') }}</th><th>{{ __('admin.p_created') }}</th></thead>
    <tbody>
    @forelse($cars as $car)
        <tr>
            <td>{{ $car->model?->brand?->name ?? '—' }}</td>
            <td>{{ $car->model?->name ?? '—' }}</td>
            <td>{{ trim(($car->generation_name ?? '').' '.($car->modification_name ?? '')) ?: '—' }}</td>
            <td>{{ $car->year ?: '—' }}</td>
            <td>{{ $car->fuel_type ? __('cars.fuel.'.$car->fuel_type) : '—' }}</td>
            <td>{{ $car->plate }}</td>
            <td>{{ $kpi['car_visits'][$car->id] ?? 0 }}</td>
            <td class="text-nowrap">{{ $fmt($car->created_at) }}</td>
        </tr>
    @empty
        <tr><td colspan="8">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
