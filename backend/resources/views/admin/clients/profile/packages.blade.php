<table class="table table-sm table-hover">
    <thead><th>ID</th><th>{{ __('admin.package') }}</th><th>{{ __('admin.car') }}</th><th>{{ __('admin.start_date') }}</th><th>{{ __('admin.end_date') }}</th><th>{{ __('admin.washes_used') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.auto_renew') }}</th></thead>
    <tbody>
    @forelse($packages as $p)
        @php($live = \Carbon\Carbon::parse($p->end_date)->isFuture() && $p->used_washes < $p->number_of_washes)
        <tr>
            <td>{{ $p->id }}</td>
            <td>{{ $p->package?->car_type ?? '—' }}</td>
            <td>{{ \App\Services\ClientProfile::carLabel($p->car) }}</td>
            <td class="text-nowrap">{{ $fmt($p->start_date) }}</td>
            <td class="text-nowrap">{{ $fmt($p->end_date) }}</td>
            <td>{{ $p->used_washes }}/{{ $p->number_of_washes }}</td>
            <td><span class="badge {{ $live ? 'bg-success' : 'bg-secondary' }}">{{ $live ? __('admin.active') : __('admin.inactive') }}</span></td>
            <td>{{ $p->rectoken ? __('admin.yes') : __('admin.no') }}</td>
        </tr>
    @empty
        <tr><td colspan="8">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
