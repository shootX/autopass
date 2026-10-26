{{-- How a fleet car washes, with the switch; a discount car picks one of the company's discount packages and shows its body type's price. --}}
<form method="POST" action="{{ $carBase.'/'.$car->id.'/mode' }}" class="d-inline" onsubmit="return confirm(@json(__('admin.cw_mode_confirm')))">
    @csrf
    <input type="hidden" name="mode" value="{{ $car->mode === 'washes' ? 'discount' : 'washes' }}">
    <button type="submit" class="btn btn-sm {{ $car->mode === 'washes' ? 'btn-outline-primary' : 'btn-outline-warning' }}" title="{{ __('admin.cw_mode_switch') }}">{{ __('admin.cw_mode_'.$car->mode) }} <i class="fas fa-right-left ms-1"></i></button>
</form>
@if($car->mode === 'discount')
    @php
        $carPackage = $discountPackages->get($car->corporate_package_id);
        $carType = $car->userCar?->model?->type;
        $carDiscount = $carPackage && $carType ? $carPackage->prices->firstWhere('car_type', $carType) : null;
    @endphp
    @if($discountPackages->isEmpty())
        <small class="d-block text-danger">{{ __('admin.cd_no_packages') }}</small>
    @else
        <form method="POST" action="{{ $carBase.'/'.$car->id.'/mode' }}" class="mt-1">
            @csrf
            <input type="hidden" name="mode" value="discount">
            <select class="form-select form-select-sm" name="discount_package_id" style="min-width:160px" aria-label="{{ __('admin.cd_package') }}" onchange="this.form.submit()">
                @unless($carPackage)
                    <option value="" selected disabled>{{ __('admin.cd_pick') }}</option>
                @endunless
                @foreach($discountPackages as $option)
                    <option value="{{ $option->id }}" @selected($carPackage?->id === $option->id)>{{ $option->title }}</option>
                @endforeach
            </select>
        </form>
        @if(! $carPackage)
            <small class="d-block text-danger">{{ __('admin.cd_car_no_package') }}</small>
        @elseif($carDiscount)
            <small class="d-block text-muted">{{ __('admin.cd_car_price', ['type' => $carType, 'percent' => $carDiscount->percent, 'pay' => $carDiscount->toPay()]) }}</small>
        @elseif($carType)
            <small class="d-block text-danger">{{ __('admin.cd_car_missing', ['type' => $carType]) }}</small>
        @else
            <small class="d-block text-muted">{{ __('admin.cd_car_unknown') }}</small>
        @endif
        @if($carPackage?->monthly_limit)
            @php($carUsed = $discountUsed->get($car->id, 0))
            <small class="d-block {{ $carUsed >= $carPackage->monthly_limit ? 'text-danger' : 'text-muted' }}">{{ __('admin.cd_car_month', ['used' => $carUsed, 'limit' => $carPackage->monthly_limit]) }}</small>
        @endif
    @endif
@endif
