{{-- How a fleet car washes, with the switch; a discount car shows its body type's price. --}}
<form method="POST" action="{{ $carBase.'/'.$car->id.'/mode' }}" class="d-inline" onsubmit="return confirm(@json(__('admin.cw_mode_confirm')))">
    @csrf
    <input type="hidden" name="mode" value="{{ $car->mode === 'washes' ? 'discount' : 'washes' }}">
    <button type="submit" class="btn btn-sm {{ $car->mode === 'washes' ? 'btn-outline-primary' : 'btn-outline-warning' }}" title="{{ __('admin.cw_mode_switch') }}">{{ __('admin.cw_mode_'.$car->mode) }} <i class="fas fa-right-left ms-1"></i></button>
</form>
@if($car->mode === 'discount')
    @php
        $carType = $car->userCar?->model?->type;
        $carDiscount = $carType ? $discountsByType->get($carType) : null;
    @endphp
    @if($carDiscount)
        <small class="d-block text-muted">{{ __('admin.cd_car_price', ['type' => $carType, 'percent' => $carDiscount->percent, 'pay' => $carDiscount->toPay()]) }}</small>
    @elseif($carType)
        <small class="d-block text-danger">{{ __('admin.cd_car_missing', ['type' => $carType]) }}</small>
    @else
        <small class="d-block text-muted">{{ __('admin.cd_car_unknown') }}</small>
    @endif
@endif
