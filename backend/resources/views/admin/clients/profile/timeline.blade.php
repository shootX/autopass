<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.date') }}</th><th>{{ __('admin.type') }}</th><th>{{ __('admin.p_details') }}</th></thead>
    <tbody>
    @forelse($timeline as $event)
        <tr>
            <td class="text-nowrap">{{ $fmt($event['at']) }}</td>
            <td><span class="badge bg-light text-dark">{{ __('admin.p_ev_'.$event['type']) }}</span></td>
            <td>
                {{ $event['text'] }}
                @isset($event['status'])<span class="badge {{ $statusClasses[$event['status']] ?? 'bg-secondary' }}">{{ $statusLabels[$event['status']] ?? $event['status'] }}</span>@endisset
            </td>
        </tr>
    @empty
        <tr><td colspan="3">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
