<table class="table table-sm table-hover">
    <thead><th>ID</th><th>{{ __('admin.date') }}</th><th>{{ __('admin.p_branch') }}</th><th>{{ __('admin.car') }}</th><th>{{ __('admin.services') }}</th><th>{{ __('admin.status') }}</th><th>{{ __('admin.p_created') }}</th></thead>
    <tbody>
    @forelse($appointments as $a)
        <tr>
            <td>{{ $a->id }}</td>
            <td class="text-nowrap">{{ \App\Services\ClientProfile::appointmentAt($a)->format('d.m.Y H:i') }}</td>
            <td>{{ $a->washing?->name ?? '—' }}</td>
            <td>{{ \App\Services\ClientProfile::carLabel($a->car) }}</td>
            <td>{{ $a->services_list ?: '—' }}</td>
            <td><span class="badge {{ $statusClasses[$a->approved] ?? 'bg-secondary' }}">{{ $statusLabels[$a->approved] ?? $a->approved }}</span></td>
            <td class="text-nowrap">{{ $fmt($a->created_at) }}</td>
        </tr>
    @empty
        <tr><td colspan="7">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
