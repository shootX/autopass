<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.date') }}</th><th>{{ __('admin.p_branch') }}</th><th>{{ __('admin.p_stars') }}</th><th>{{ __('admin.p_text') }}</th><th>{{ __('admin.p_published') }}</th></thead>
    <tbody>
    @forelse($reviews as $r)
        <tr>
            <td class="text-nowrap">{{ $fmt($r->created_at) }}</td>
            <td>{{ $r->washing?->name ?? '—' }}</td>
            <td class="text-nowrap">{{ str_repeat('★', (int) $r->stars) }}{{ str_repeat('☆', max(0, 5 - (int) $r->stars)) }}</td>
            <td>{{ $r->text }}</td>
            <td>{{ $r->status ? __('admin.yes') : __('admin.no') }}</td>
        </tr>
    @empty
        <tr><td colspan="5">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
