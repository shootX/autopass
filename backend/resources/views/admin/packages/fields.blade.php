{{-- App package fields, add and edit. A washes package has prices for 1-12 months; a discount package
     has the full price, the percent and one price for its 30 days (count_washes 0 = unlimited). --}}
@php($package = $package ?? null)
@php($type = old('type', $package?->type ?? \App\Models\Package::TYPE_WASHES))
<div class="form-group">
    <label for="packageType">{{ __('admin.package_type') }}</label>
    <select id="packageType" name="type" class="form-select">
        <option value="{{ \App\Models\Package::TYPE_WASHES }}" @selected($type === \App\Models\Package::TYPE_WASHES)>{{ __('admin.package_type_washes') }}</option>
        <option value="{{ \App\Models\Package::TYPE_DISCOUNT }}" @selected($type === \App\Models\Package::TYPE_DISCOUNT)>{{ __('admin.package_type_discount') }}</option>
    </select>
</div>

<div class="form-group mt-3">
    <label for="carType">{{ __('admin.body_type') }}</label>
    <select id="carType" name="car_type" class="form-select">
        @forelse($carTypes as $carType)
            <option value="{{ $carType }}" @selected(old('car_type', $package?->car_type) == $carType)>{{ \App\Models\BodyType::labelFor($carType) }}</option>
        @empty
            <option disabled selected>{{ __('admin.no_body_types') }}</option>
        @endforelse
    </select>
</div>

@if(count($carTypes) > 0)
    <div class="form-group mt-3">
        <label for="count_washes" data-for-type="washes">{{ __('admin.wash_count') }}</label>
        <label for="count_washes" data-for-type="discount">{{ __('admin.discount_wash_limit') }}</label>
        <input class="form-control" id="count_washes" type="number" min="0" required name="count_washes" value="{{ old('count_washes', $package?->count_washes ?? 0) }}">
    </div>

    <div data-for-type="washes">
        @foreach([1 => 'price1', 3 => 'price2', 6 => 'price3', 12 => 'price4'] as $month => $field)
            <div class="form-group mt-3">
                <label for="{{ $field }}">{{ __('admin.price_'.$month.'_label') }}</label>
                <input class="form-control" id="{{ $field }}" type="number" min="0" step="0.01" required name="{{ $field }}" value="{{ old($field, $package?->priceForMonth($month)?->price ?? 0) }}">
            </div>
        @endforeach
    </div>

    <div data-for-type="discount">
        <div class="form-group mt-3">
            <label for="full_price">{{ __('admin.discount_full_price') }}</label>
            <input class="form-control" id="full_price" type="number" min="0" step="0.01" required name="full_price" value="{{ old('full_price', $package?->full_price ?? 0) }}">
        </div>
        <div class="form-group mt-3">
            <label for="percent">{{ __('admin.discount_percent') }}</label>
            <input class="form-control" id="percent" type="number" min="1" max="100" required name="percent" value="{{ old('percent', $package?->percent ?? 10) }}">
        </div>
        <div class="form-group mt-3">
            <label for="price_discount">{{ __('admin.discount_package_price') }}</label>
            <input class="form-control" id="price_discount" type="number" min="0" step="0.01" required name="price_discount" value="{{ old('price_discount', $package?->isDiscount() ? $package->priceForMonth(1)?->price : 0) }}">
        </div>
    </div>
@endif

<script>
    (function () {
        const select = document.getElementById('packageType');
        const show = () => document.querySelectorAll('[data-for-type]').forEach((el) => {
            const on = el.dataset.forType === select.value;
            el.style.display = on ? '' : 'none';
            // Hidden fields are neither required nor sent.
            el.querySelectorAll('input').forEach((input) => { input.disabled = !on; });
        });
        select.addEventListener('change', show);
        show();
    })();
</script>
