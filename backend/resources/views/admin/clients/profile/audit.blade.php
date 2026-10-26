<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.date') }}</th><th>{{ __('admin.p_admin') }}</th><th>{{ __('admin.p_action') }}</th><th>{{ __('admin.p_field') }}</th><th>{{ __('admin.p_old') }}</th><th>{{ __('admin.p_new') }}</th></thead>
    <tbody>
    @forelse($audit as $l)
        <tr>
            <td class="text-nowrap">{{ $fmt($l->created_at) }}</td>
            <td>{{ trim(($l->admin_name ?? '').' '.($l->admin_surname ?? '')) ?: ($l->admin_id ? '#'.$l->admin_id : '—') }}</td>
            <td>{{ $l->action }}</td>
            <td>{{ $l->field ?? '—' }}</td>
            <td>{{ $l->old_value ?? '—' }}</td>
            <td>{{ $l->new_value ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
