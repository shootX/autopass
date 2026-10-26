<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.p_bought') }}</th><th>{{ __('admin.p_voucher') }}</th><th>{{ __('admin.price') }}</th><th>{{ __('admin.p_code') }}</th><th>{{ __('admin.p_used') }}</th><th>{{ __('admin.p_partner') }}</th></thead>
    <tbody>
    @forelse($vouchers as $v)
        <tr>
            <td class="text-nowrap">{{ $fmt($v->created_at) }}</td>
            <td>{{ $v->voucher?->name ?? '—' }}</td>
            <td>{{ $v->voucher?->price ?? '—' }}</td>
            <td><code>{{ $v->code }}</code></td>
            <td>{{ $v->used_at ? $fmt($v->used_at) : __('admin.no') }}</td>
            <td>{{ $v->partner?->name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
<h6 class="mt-4">{{ __('admin.tickets') }}</h6>
<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.p_bought') }}</th><th>{{ __('admin.p_ticket') }}</th><th>{{ __('admin.price') }}</th></thead>
    <tbody>
    @forelse($tickets as $t)
        <tr><td class="text-nowrap">{{ $fmt($t->created_at) }}</td><td>{{ $t->name }}</td><td>{{ $t->price }}</td></tr>
    @empty
        <tr><td colspan="3">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
