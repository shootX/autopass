{{-- A company's washes: the pool, its own packages and the ledger. $manage adds the admin forms. --}}
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
                            @foreach($packages->where('is_active', true) as $package)
                                <option value="{{ $package->id }}">{{ $package->title }} · {{ $package->washes }}</option>
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
                        <input class="form-control" type="number" name="amount" required min="-100000" max="100000" placeholder="-5 / 5">
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
    <div class="card-header">{{ __('admin.cw_packages') }}</div>
    <div class="card-body table-responsive">
        <small class="d-block mb-2">{{ __($manage ? 'admin.cw_packages_hint' : 'admin.cw_packages_hint_company') }}</small>
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>{{ __('admin.cw_package_title') }}</th>
                <th>{{ __('admin.cw_package_washes') }}</th>
                <th>{{ __('admin.cw_package_price') }}</th>
                <th>{{ __('admin.status') }}</th>
                @if($manage)<th>{{ __('admin.actions') }}</th>@endif
            </tr>
            </thead>
            <tbody>
            @forelse($packages as $package)
                @if($manage)
                    <tr>
                        <td><input class="form-control form-control-sm" form="cw-pkg-{{ $package->id }}" name="title" required maxlength="120" value="{{ $package->title }}"></td>
                        <td><input class="form-control form-control-sm" form="cw-pkg-{{ $package->id }}" type="number" name="washes" required min="1" max="100000" value="{{ $package->washes }}"></td>
                        <td><input class="form-control form-control-sm" form="cw-pkg-{{ $package->id }}" type="number" name="price" min="0" step="0.01" value="{{ $package->price }}"></td>
                        <td><label><input form="cw-pkg-{{ $package->id }}" type="checkbox" name="is_active" value="1" @checked($package->is_active)> {{ __('admin.cw_package_active') }}</label></td>
                        <td>
                            <form id="cw-pkg-{{ $package->id }}" method="post" action="{{ route('admin.corporate.packages.save', [$corporate, $package]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-save"></i></button></form>
                            <form method="post" action="{{ route('admin.corporate.packages.delete', [$corporate, $package]) }}" class="d-inline" onsubmit="return confirm(@json(__('admin.cw_package_delete_confirm')))">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $package->title }}</td>
                        <td>{{ $package->washes }}</td>
                        <td>{{ $package->price !== null ? number_format((float) $package->price, 2).' ₾' : '—' }}</td>
                        <td><span class="badge {{ $package->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $package->is_active ? __('admin.active') : __('admin.cw_package_inactive') }}</span></td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ $manage ? 5 : 4 }}" class="umami-empty">{{ __('admin.cw_packages_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        @if($manage)
            <form class="row g-2 align-items-end" method="post" action="{{ route('admin.corporate.packages.store', $corporate) }}">
                @csrf
                <div class="col-md-5"><input class="form-control" name="title" required maxlength="120" placeholder="{{ __('admin.cw_package_title') }}"></div>
                <div class="col-md-2"><input class="form-control" type="number" name="washes" required min="1" max="100000" placeholder="{{ __('admin.cw_package_washes') }}"></div>
                <div class="col-md-2"><input class="form-control" type="number" name="price" min="0" step="0.01" placeholder="{{ __('admin.cw_package_price') }}"></div>
                <div class="col-md-3"><button class="btn btn-outline-primary w-100" type="submit">{{ __('admin.cw_package_add') }}</button></div>
            </form>
        @endif
    </div>
</div>

<div class="card mt-4 mb-4">
    <div class="card-header">{{ __('admin.cw_ledger') }}</div>
    <div class="card-body table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ __('admin.cw_date') }}</th>
                <th>{{ __('admin.cw_type') }}</th>
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
                    <td>{{ $move->plate ? \App\Services\FleetCars::formatPlate($move->plate) : '—' }}</td>
                    <td class="{{ $move->amount < 0 ? 'text-danger' : 'text-success' }}">{{ $move->amount > 0 ? '+'.$move->amount : $move->amount }}</td>
                    <td>{{ $move->balance_after }}</td>
                    <td>{{ $move->car_balance_after ?? '—' }}</td>
                    <td>{{ $move->type === 'wash' ? ($move->wash?->washing?->name ?? __('admin.cw_actor_manager')) : __('admin.cw_actor_'.$move->actor) }}</td>
                    <td>{{ $move->package?->title ?? $move->note ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="umami-empty">{{ __('admin.cw_ledger_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $moves->fragment('washes')->links('pagination::bootstrap-5') }}
    </div>
</div>

@if($manage)
    @push('js')
        <script>
            $('#cw_package').on('change', function () {
                $('#cw_amount').prop('disabled', this.value !== '').prop('required', this.value === '');
            }).trigger('change');
        </script>
    @endpush
@endif
