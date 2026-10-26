{{-- The company's active discount package: full price, percent and what the driver pays per body type. --}}
<div id="discounts"></div>
<div class="card mt-4">
    <div class="card-header">{{ __('admin.cd_title') }}@if($discount) · {{ $discount->title }}@endif</div>
    <div class="card-body table-responsive">
        <small class="d-block mb-2">{{ __($manage ? 'admin.cd_hint' : 'admin.cd_hint_company') }}</small>
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>{{ __('admin.body_type') }}</th>
                <th>{{ __('admin.cd_full_price') }}</th>
                <th>{{ __('admin.cd_percent') }}</th>
                <th>{{ __('admin.cd_to_pay') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($discount?->prices ?? [] as $row)
                <tr>
                    <td>{{ $row->car_type }}</td>
                    <td>{{ $row->full_price }} ₾</td>
                    <td>{{ $row->percent }}%</td>
                    <td class="fw-bold">{{ $row->toPay() }} ₾</td>
                </tr>
            @empty
                <tr><td colspan="4" class="umami-empty">{{ __('admin.cd_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
