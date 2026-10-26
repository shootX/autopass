<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.date') }}</th><th>{{ __('admin.p_branch') }}</th><th>{{ __('admin.car') }}</th><th>{{ __('admin.manager') }}</th><th>{{ __('admin.p_source') }}</th><th>{{ __('admin.package') }}</th></thead>
    <tbody>
    @forelse($washes as $w)
        <tr>
            <td class="text-nowrap">{{ $fmt($w->created_at) }}</td>
            <td>{{ $w->washing?->name ?? '—' }}</td>
            <td>{{ $w->userPackage?->car?->plate ?? '—' }}</td>
            <td>{{ $w->manager?->titleName() ?? '—' }}</td>
            <td>{{ $w->walk_in ? __('admin.p_walk_in') : __('admin.p_by_booking') }}</td>
            <td>#{{ $w->user_package_id }}</td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
