{{-- A company's washes: the pool, its packages and discounts, the ledger and the wash history. $manage adds the admin forms. --}}
<div id="washes"></div>
@error('washes')
    <div class="alert alert-danger mt-4 mb-0">{{ $message }}</div>
@enderror

<div class="row">
    <div class="col-lg-4 d-flex">
        <div class="card mt-4 w-100">
            <div class="card-header">{{ __('admin.cw_title') }}</div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                    <div><div class="text-muted">{{ __('admin.cw_total') }}</div><div class="fs-3 fw-bold">{{ $balance['total'] }}</div></div>
                    <div><div class="text-muted">{{ __('admin.cw_allocated') }}</div><div class="fs-3 fw-bold">{{ $balance['allocated'] }}</div></div>
                    <div><div class="text-muted">{{ __('admin.cw_free') }}</div><div class="fs-3 fw-bold">{{ $balance['free'] }}</div></div>
                </div>
                @if(count($balance['types']))
                    <table class="table table-sm mt-3 mb-0">
                        <thead><tr><th>{{ __('admin.body_type') }}</th><th>{{ __('admin.cw_total') }}</th><th>{{ __('admin.cw_allocated') }}</th><th>{{ __('admin.cw_free') }}</th></tr></thead>
                        <tbody>
                        @foreach($balance['types'] as $type => $row)
                            <tr><td>{{ \App\Models\BodyType::labelFor($type) }}</td><td>{{ $row['total'] }}</td><td>{{ $row['allocated'] }}</td><td>{{ $row['free'] }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
                <small class="d-block mt-2">{{ __('admin.cw_balance_hint') }}</small>
            </div>
        </div>
    </div>
    @if($manage)
        <div class="col-lg-4 d-flex">
            <div class="card mt-4 w-100">
                <div class="card-header">{{ __('admin.cw_credit') }}</div>
                <div class="card-body">
                    <form action="{{ route('admin.corporate.washes.credit', $corporate) }}" method="post">
                        @csrf
                        <select class="form-control" name="corporate_package_id" id="cw_package">
                            <option value="">{{ __('admin.cw_credit_manual') }}</option>
                            @foreach($packages->where('is_active', true)->where('type', 'washes') as $package)
                                <option value="{{ $package->id }}">{{ $package->title }} · {{ $package->washes }} · {{ \App\Models\BodyType::labelFor($package->car_type) }}</option>
                            @endforeach
                        </select>
                        <select class="form-control mt-2" name="car_type" id="cw_car_type" aria-label="{{ __('admin.body_type') }}">
                            <option value="">{{ __('admin.body_type') }}</option>
                            @foreach(\App\Models\BodyType::options() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input class="form-control mt-2" type="number" name="amount" id="cw_amount" min="1" max="100000" placeholder="{{ __('admin.cw_amount') }}">
                        <input class="form-control mt-2" name="note" maxlength="255" placeholder="{{ __('admin.cw_note') }}">
                        <small class="d-block mt-1">{{ __('admin.cw_credit_hint') }}</small>
                        <button class="btn btn-primary mt-2" type="submit">{{ __('admin.cw_credit_btn') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4 d-flex">
            <div class="card mt-4 w-100">
                <div class="card-header">{{ __('admin.cw_correct') }}</div>
                <div class="card-body">
                    <form action="{{ route('admin.corporate.washes.correct', $corporate) }}" method="post" onsubmit="return confirm(@json(__('admin.cw_correct_confirm')))">
                        @csrf
                        <select class="form-control" name="car_type" required aria-label="{{ __('admin.body_type') }}">
                            <option value="">{{ __('admin.body_type') }}</option>
                            @foreach(\App\Models\BodyType::options() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input class="form-control mt-2" type="number" name="amount" required min="-100000" max="100000" placeholder="-5 / 5">
                        <input class="form-control mt-2" name="note" required maxlength="255" placeholder="{{ __('admin.cw_note') }}">
                        <small class="d-block mt-1">{{ __('admin.cw_correct_hint') }}</small>
                        <button class="btn btn-outline-secondary mt-2" type="submit">{{ __('admin.cw_correct') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>{{ __('admin.cw_packages') }}</div>
        @if($manage)
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.packages.corporate.add', ['company' => $corporate->id]) }}"><i class="fa fa-plus"></i> {{ __('admin.cp_type_washes') }}</a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.packages.corporate.add', ['company' => $corporate->id, 'type' => 'discount']) }}"><i class="fa fa-plus"></i> {{ __('admin.cp_type_discount') }}</a>
            </div>
        @endif
    </div>
    <div class="card-body table-responsive">
        <small class="d-block mb-2">{{ __($manage ? 'admin.cw_packages_hint' : 'admin.cw_packages_hint_company') }}</small>
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>{{ __('admin.cw_package_title') }}</th>
                <th>{{ __('admin.cp_type') }}</th>
                <th>{{ __('admin.cp_terms') }}</th>
                <th>{{ __('admin.cw_package_price') }}</th>
                <th>{{ __('admin.status') }}</th>
                @if($manage)<th>{{ __('admin.actions') }}</th>@endif
            </tr>
            </thead>
            <tbody>
            @forelse($packages as $package)
                <tr>
                    <td>{{ $package->title }}</td>
                    <td>{{ __('admin.cp_type_'.$package->type) }}</td>
                    <td>@include('admin.packages.corporate.terms')</td>
                    <td>{{ $package->price !== null ? number_format((float) $package->price, 2).' ₾' : '—' }}</td>
                    <td><span class="badge {{ $package->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $package->is_active ? __('admin.active') : __('admin.cw_package_inactive') }}</span></td>
                    @if($manage)
                        <td><a class="btn btn-sm btn-primary" href="{{ route('admin.packages.corporate.edit', $package) }}" title="{{ __('admin.edit') }}"><i class="fas fa-edit"></i></a></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $manage ? 6 : 5 }}" class="umami-empty">{{ __('admin.cw_packages_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('admin.corporate.discounts')

<div class="card mt-4">
    <div class="card-header">{{ __('admin.cw_ledger') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ __('admin.cw_date') }}</th>
                <th>{{ __('admin.cw_type') }}</th>
                <th>{{ __('admin.body_type') }}</th>
                <th>{{ __('admin.plate') }}</th>
                <th>{{ __('admin.cw_change') }}</th>
                <th>{{ __('admin.cw_pool_after') }}</th>
                <th>{{ __('admin.cw_car_after') }}</th>
                <th>{{ __('admin.cw_by') }}</th>
                <th>{{ __('admin.cw_note') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($moves as $move)
                <tr>
                    <td>{{ $move->created_at?->format('d.m.Y H:i') }}</td>
                    <td>{{ __('admin.cw_type_'.$move->type) }}</td>
                    <td>{{ $move->car_type ? \App\Models\BodyType::labelFor($move->car_type) : '—' }}</td>
                    <td>{{ $move->plate ? \App\Services\FleetCars::formatPlate($move->plate) : '—' }}</td>
                    <td class="{{ $move->amount < 0 ? 'text-danger' : 'text-success' }}">{{ $move->amount > 0 ? '+'.$move->amount : $move->amount }}</td>
                    <td>{{ $move->balance_after }}</td>
                    <td>{{ $move->car_balance_after ?? '—' }}</td>
                    <td>{{ $move->type === 'wash' ? ($move->wash?->washing?->name ?? __('admin.cw_actor_manager')) : __('admin.cw_actor_'.$move->actor) }}</td>
                    <td>{{ $move->invoice ? __('admin.inv_credit_note', ['number' => $move->invoice->number]) : ($move->package?->title ?? $move->note ?? '') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="umami-empty">{{ __('admin.cw_ledger_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $moves->fragment('washes')->links('pagination::bootstrap-5') }}
    </div>
</div>

@include('admin.corporate.history')

@if($manage)
    @push('js')
        <script>
            // A package brings its own amount and body type; a manual credit needs both.
            $('#cw_package').on('change', function () {
                $('#cw_amount, #cw_car_type').prop('disabled', this.value !== '').prop('required', this.value === '');
            }).trigger('change');
        </script>
    @endpush
@endif
