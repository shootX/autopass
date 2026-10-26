<h6>{{ __('admin.p_card_payments') }}</h6>
<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.date') }}</th><th>{{ __('admin.type') }}</th><th>{{ __('admin.p_amount') }}</th><th>{{ __('admin.status') }}</th></thead>
    <tbody>
    @forelse($payments as $p)
        <tr @if($p->test_mode) class="text-muted" @endif>
            <td class="text-nowrap">{{ $fmt($p->paid_at ?? $p->created_at) }}</td>
            <td>{{ $p->type }} @if($p->test_mode)<span class="badge bg-light text-dark">test</span>@endif</td>
            <td>{{ number_format((float) $p->amount, 2) }} {{ $p->currency }}</td>
            <td><span class="badge {{ $p->status === 'succeeded' ? 'bg-success' : 'bg-secondary' }}">{{ $p->status }}</span></td>
        </tr>
    @empty
        <tr><td colspan="4">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
<h6 class="mt-4">{{ __('admin.p_points_moves') }}</h6>
<table class="table table-sm table-hover">
    <thead><th>{{ __('admin.date') }}</th><th>{{ __('admin.type') }}</th><th>{{ __('admin.p_amount') }}</th><th>{{ __('admin.p_system') }}</th><th>{{ __('admin.p_comment') }}</th></thead>
    <tbody>
    @forelse($transactions as $t)
        <tr>
            <td class="text-nowrap">{{ $fmt($t->created_at) }}</td>
            <td><span class="badge {{ $t->type === 'income' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $t->type === 'income' ? __('admin.p_income') : __('admin.p_outcome') }}</span></td>
            <td>{{ $t->type === 'income' ? '+' : '−' }}{{ (int) $t->amount }}</td>
            <td>{{ $t->system }}</td>
            <td>{{ $t->comment }}</td>
        </tr>
    @empty
        <tr><td colspan="5">{{ __('admin.dash_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
