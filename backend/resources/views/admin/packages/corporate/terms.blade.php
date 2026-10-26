{{-- What a corporate package gives: its washes, or the price per body type of a discount package. --}}
@if($package->isDiscount())
    <div class="small fw-semibold">{{ $package->monthly_limit ? __('admin.cp_limit_value', ['count' => $package->monthly_limit]) : __('admin.cp_limit_unlimited') }}</div>
    @forelse($package->prices as $row)
        <div class="small">{{ __('admin.cp_price_row', ['type' => $row->car_type, 'full' => $row->full_price, 'percent' => $row->percent, 'pay' => $row->toPay()]) }}</div>
    @empty
        —
    @endforelse
@else
    {{ __('admin.cp_washes_count', ['count' => $package->washes]) }}
@endif
