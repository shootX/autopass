<div class="row">
    <div class="col-lg-4 d-flex">
        <div class="card mt-4 w-100">
            <div class="card-header">{{ __('admin.fleet_manual') }}</div>
            <div class="card-body">
                @php
                    // Brands of the car catalog; models, years and body types load as they are picked.
                    $catalog = app(\App\Services\CarCatalog::class)->brands();
                @endphp
                <form action="{{ $storeUrl }}" method="post">
                    @csrf
                    <div class="form-group mt-3">
                        <label for="fleet_plate">{{ __('admin.plate') }}<span class="text-danger">*</span></label>
                        <input class="form-control" id="fleet_plate" name="plate" required maxlength="14" placeholder="AA - 123 - BB" value="{{ old('plate') }}">
                        <small>{{ __('admin.fleet_plate_format') }}</small>
                        @error('plate')<small class="d-block" style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group mt-3">
                        <label for="fleet_phone">{{ __('admin.fleet_driver_phone') }}<span class="text-danger">*</span></label>
                        <input class="form-control" id="fleet_phone" name="phone" required maxlength="32" placeholder="5XX XX XX XX" value="{{ old('phone') }}">
                        <small>{{ __('admin.fleet_phone_hint') }}</small>
                        @error('phone')<small class="d-block" style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group mt-3">
                        <label for="fleet_brand">{{ __('admin.brand') }}<span class="text-danger">*</span></label>
                        <select class="form-control" id="fleet_brand" name="brand_id" required>
                            <option value="">{{ __('admin.select_brand') }}</option>
                            @foreach($catalog as $brand)
                                <option value="{{ $brand['id'] }}" @selected((string) old('brand_id') === (string) $brand['id'])>{{ $brand['name'] }}</option>
                            @endforeach
                        </select>
                        @error('brand_id')<small style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group mt-3">
                        <label for="fleet_model">{{ __('admin.model') }}<span class="text-danger">*</span></label>
                        <select class="form-control" id="fleet_model" name="model_id" required disabled>
                            <option value="">{{ __('admin.select_model') }}</option>
                        </select>
                        @error('model_id')<small style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group mt-3">
                        <label for="fleet_year">{{ __('admin.fleet_year') }}<span class="text-danger">*</span></label>
                        <select class="form-control" id="fleet_year" name="year" required disabled>
                            <option value="">{{ __('admin.fleet_select_year') }}</option>
                        </select>
                        @error('year')<small style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    {{-- Always the company's pick from every type; one geoapi.ge type for the year is preselected. --}}
                    <div class="form-group mt-3">
                        <label for="fleet_body">{{ __('admin.body_type') }}<span class="text-danger">*</span></label>
                        <select class="form-control" id="fleet_body" name="body_type" required>
                            <option value="">{{ __('admin.fleet_select_body') }}</option>
                            @foreach(\App\Models\BodyType::options() as $key => $label)
                                <option value="{{ $key }}" @selected(old('body_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="d-block">{{ __('admin.fleet_body_hint') }}</small>
                        @error('body_type')<small style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    <div class="small mt-2 text-danger" id="fleet_catalog_error" hidden>{{ __('admin.fleet_catalog_failed') }}</div>
                    <button class="btn btn-primary mt-3" type="submit">{{ __('admin.add') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4 d-flex">
        <div class="card mt-4 w-100">
            <div class="card-header">{{ __('admin.fleet_excel') }}</div>
            <div class="card-body">
                <form action="{{ $importUrl }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <label class="btn btn-outline-secondary" for="fleet_file">{{ __('admin.fleet_choose_file') }}</label>
                    <input id="fleet_file" class="visually-hidden" type="file" name="file" accept=".xlsx,.xls,.csv" required>
                    <span id="fleet_file_name" class="d-block mt-2">{{ __('admin.fleet_no_file') }}</span>
                    @error('file')<small style="color:red;">{{ $message }}</small>@enderror
                    <button class="btn btn-primary mt-3" type="submit">{{ __('admin.fleet_upload') }}</button>
                    <a class="btn btn-outline-secondary mt-3" href="{{ $templateUrl }}">{{ __('admin.fleet_template') }}</a>
                </form>
                <small class="d-block mt-2">{{ __('admin.fleet_excel_hint') }}</small>
            </div>
        </div>
    </div>
    <div class="col-lg-4 d-flex">
        <div class="card mt-4 w-100">
            <div class="card-header">{{ __('admin.fleet_api') }}</div>
            <div class="card-body">
                @php
                    // The key is shown once, right after it was issued; afterwards only its last 4 chars.
                    $token = (string) session('corporate_api_token', '');
                    $masked = str_repeat('•', 12).(string) $corporate->api_token_last4;
                    if ($token === '') {
                        $token = $masked;
                    }
                @endphp
                <div class="mb-2"><code id="fleet-token" style="word-break:break-all;" data-full="{{ $token }}" data-masked="{{ $masked }}">{{ $masked }}</code></div>
                <button class="btn btn-outline-secondary btn-sm" type="button" id="fleet-token-toggle">{{ __('admin.fleet_token_show') }}</button>
                <form action="{{ $tokenUrl }}" method="post" class="d-inline" onsubmit="return confirm(@json(__('admin.corporate_token_confirm')))">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('admin.corporate_token_reset_btn') }}</button>
                </form>
                <pre class="mt-3 mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;">POST {{ url('/api/corporate/vehicles') }}
X-Api-Key: <span id="fleet-token-inline">{{ $masked }}</span>
{"plate":"AB123CD","brand":"Toyota","model":"Camry","year":2019,"body_type":"sedan","phone":"555 12 34 56"}
{"plate":"AB124CD","brand":"Toyota","model":"Avensis","year":2016,"body_type":"wagon","phone":"555 12 34 57"}

PATCH {{ url('/api/corporate/vehicles/AB123CD') }}
{"mode":"washes","washes":4}

GET {{ url('/api/corporate/balance') }}</pre>
            </div>
        </div>
    </div>
</div>

@if(session('fleet_import_errors'))
    <div class="alert alert-warning mt-4 mb-0">
        <div>{{ __('admin.fleet_import_errors') }}</div>
        <ul class="mb-0">
            @foreach(session('fleet_import_errors') as $row)
                <li>{{ __('admin.fleet_import_row', ['row' => $row['row'], 'plate' => $row['plate'] ?: '—']) }}: {{ __('admin.fleet_reason_'.$row['reason']) }}</li>
            @endforeach
        </ul>
    </div>
@endif
@error('fleet')
    <div class="alert alert-danger mt-4 mb-0">{{ $message }}</div>
@enderror

@php
    $statusBadge = ['invited' => 'bg-warning text-dark', 'active' => 'bg-success', 'blocked' => 'bg-danger'];
    $washBalance = \App\Services\CorporateWashes::balance($corporate);
    // A car gets washes from its own body type's pool only.
    $freeByType = collect($washBalance['types'])->map(fn ($row) => $row['free']);
    $discountPackages = $corporate->discountPackages()->with('prices')->get()->keyBy('id');
    $discountUsed = \App\Services\CorporateDiscounts::usedThisMonthByCar($corporate);
    $bodyOptions = empty($adminBody) ? [] : \App\Models\BodyType::options();
@endphp
<div class="card mt-4 mb-4">
    <div class="card-header">{{ __('admin.fleet') }} · {{ $cars->total() }} <span class="text-muted ms-2">{{ __('admin.cw_free') }}: {{ $washBalance['free'] }}</span></div>
    <div class="card-body table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ __('admin.plate') }}</th>
                <th>{{ __('admin.brand') }}</th>
                <th>{{ __('admin.model') }}</th>
                <th>{{ __('admin.fleet_driver_phone') }}</th>
                <th>{{ __('admin.status') }}</th>
                <th>{{ __('admin.cw_mode') }}</th>
                <th>{{ __('admin.cw_car_washes') }}</th>
                <th>{{ __('admin.fleet_source') }}</th>
                <th>{{ __('admin.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($cars as $car)
                <tr>
                    <td>{{ \App\Services\FleetCars::formatPlate($car->plate) }}</td>
                    <td>{{ $car->brand }}</td>
                    <td>{{ $car->model }}@if($car->year) <span class="text-muted">{{ $car->year }}</span>@endif
                        @include('admin.corporate.fleet-body')</td>
                    <td>{{ $car->driver_phone ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $statusBadge[$car->status] ?? 'bg-secondary' }}">{{ __('admin.fleet_status_'.$car->status) }}</span>
                        @if($car->activation_error)
                            <small class="d-block text-danger">{{ __('admin.fleet_activation_'.$car->activation_error) }}</small>
                        @elseif($car->status === 'invited' && ! $car->driver_phone)
                            <small class="d-block text-muted">{{ __('admin.fleet_invite_no_phone') }}</small>
                        @endif
                    </td>
                    <td>@include('admin.corporate.fleet-mode')</td>
                    <td>
                        @if($car->mode === 'washes')
                            <a class="btn btn-sm btn-outline-secondary fleet-washes" data-id="{{ $car->id }}" data-washes="{{ (int) $car->washes_left }}" data-free="{{ (int) ($freeByType[$car->carType()] ?? 0) }}" title="{{ __('admin.cw_allocate') }}">{{ (int) $car->washes_left }} <i class="fas fa-pen ms-1"></i></a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ __('admin.fleet_source_'.$car->source) }}</td>
                    <td>
                        <div class="btn-group">
                            <a class="btn btn-sm btn-outline-secondary fleet-driver" data-id="{{ $car->id }}" data-phone="{{ $car->driver_phone }}" title="{{ __('admin.fleet_change_driver') }}"><i class="fas fa-phone"></i></a>
                            @if($car->status === 'invited' && $car->driver_phone)
                                <form method="POST" action="{{ $carBase.'/'.$car->id.'/invite' }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('admin.fleet_invite_resend') }}"><i class="fas fa-paper-plane"></i></button></form>
                            @endif
                            <form method="POST" action="{{ $carBase.'/'.$car->id.'/block' }}" class="d-inline" onsubmit="return confirm(@json($car->status === 'blocked' ? __('admin.unblock_confirm') : __('admin.block_confirm')))">@csrf<button type="submit" class="btn btn-sm {{ $car->status === 'blocked' ? 'btn-warning' : 'btn-dark' }}" title="{{ $car->status === 'blocked' ? __('admin.action_unblock') : __('admin.action_block') }}"><i class="fas fa-ban"></i></button></form>
                            <a class="btn btn-sm btn-danger remove-car" data-id="{{ $car->id }}"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="umami-empty">{{ __('admin.fleet_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $cars->links('pagination::bootstrap-5') }}
    </div>
</div>

@push('js')
    <script>
        (function () {
            const base = @json($catalogUrl);
            const old = { model: @json(old('model_id')), year: @json(old('year')) };
            const brand = $('#fleet_brand'), model = $('#fleet_model'), year = $('#fleet_year'), body = $('#fleet_body');
            const placeholder = {
                model: @json(__('admin.select_model')), year: @json(__('admin.fleet_select_year')),
            };
            let request = 0;
            let preselected = false; // the body type came from geoapi.ge, not from the company's own pick

            function fill(select, rows, empty, selected) {
                select.empty().append($('<option value="">').text(empty));
                rows.forEach(row => select.append($('<option>').val(row.value).text(row.label).attr('data-source', row.source || null)));
                if (selected !== null && selected !== undefined) select.val(String(selected));
                select.prop('disabled', rows.length === 0);
            }
            function failed() { $('#fleet_catalog_error').prop('hidden', false); }
            function pick() {
                return { source: model.find(':selected').attr('data-source') || 'local', brand_id: brand.val(), model_id: model.val() };
            }
            function clearPreselected() {
                if (preselected) body.val('');
                preselected = false;
            }
            // Each pick clears the ones after it; a late answer for an older pick is dropped.
            function load(url, query, done) {
                const ticket = ++request;
                $('#fleet_catalog_error').prop('hidden', true);
                $.getJSON(url, query).done(data => { if (ticket === request) done(data); }).fail(() => { if (ticket === request) failed(); });
            }
            function loadModels() {
                fill(model, [], placeholder.model); fill(year, [], placeholder.year); clearPreselected();
                if (!brand.val()) return;
                load(base + '/brands/' + brand.val() + '/models', {}, data => {
                    fill(model, data.models.map(m => ({ value: m.id, label: m.name, source: m.source })), placeholder.model, old.model);
                    old.model = null;
                    if (model.val()) loadYears();
                });
            }
            function loadYears() {
                fill(year, [], placeholder.year); clearPreselected();
                if (!model.val()) return;
                load(base + '/years', pick(), data => {
                    fill(year, data.years.map(y => ({ value: y, label: y })), placeholder.year, old.year);
                    old.year = null;
                    if (year.val()) loadBody();
                });
            }
            // A year geoapi.ge names one body type for preselects it; the company's own pick is never changed.
            function loadBody() {
                clearPreselected();
                if (!year.val() || body.val()) return;
                load(base + '/body-types', Object.assign(pick(), { year: year.val() }), data => {
                    if (data.status !== 'confirmed' || body.val()) return;
                    body.val(data.body_types[0].key);
                    preselected = true;
                });
            }
            brand.on('change', loadModels);
            model.on('change', loadYears);
            year.on('change', loadBody);
            body.on('change', () => { preselected = false; });
            loadModels();
        })();

        $('#fleet_file').on('change', function () {
            const file = this.files && this.files[0];
            $('#fleet_file_name').text(file ? file.name : @json(__('admin.fleet_no_file')));
        });

        $('#fleet-token-toggle').on('click', function () {
            const node = $('#fleet-token');
            const open = node.attr('data-open') === '1';
            const next = open ? node.attr('data-masked') : node.attr('data-full');
            node.text(next).attr('data-open', open ? '0' : '1');
            $('#fleet-token-inline').text(next);
            $(this).text(open ? @json(__('admin.fleet_token_show')) : @json(__('admin.fleet_token_hide')));
        });

        $('#fleet_plate').on('input', function () {
            const value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
            const part1 = value.slice(0, 2);
            const part2 = value.slice(2, 5);
            const part3 = value.slice(5, 7);
            this.value = part1 + (part2 ? ' - ' + part2 : '') + (part3 ? ' - ' + part3 : '');
        });

        $('.remove-car').on('click', function () {
            let id = $(this).data('id');
            $.confirm({
                title: I18N.delete_fleet_car_title,
                content: I18N.delete_fleet_car_confirm,
                type: 'red',
                buttons: {
                    yes: { text: I18N.yes, btnClass: 'btn-red', action: function () {
                        window.deleteRequest(@json($carBase) + '/' + id + '/delete');
                    }},
                    no: { text: I18N.no }
                }
            });
        });

        $('.fleet-washes').on('click', function () {
            const id = $(this).data('id');
            const current = Number($(this).data('washes') || 0);
            const free = Number($(this).data('free') || 0);
            $.confirm({
                title: I18N.cw_allocate,
                content: '<div class="form-group"><label for="fleet_washes_input">' + I18N.cw_car_washes + '</label>'
                    + '<input class="form-control" id="fleet_washes_input" type="number" min="0" max="' + (current + free) + '">'
                    + '<small class="d-block mt-1">' + I18N.cw_allocate_hint.replace(':free', free) + '</small></div>',
                onContentReady: function () { this.$content.find('#fleet_washes_input').val(current).trigger('focus'); },
                buttons: {
                    yes: { text: I18N.save, btnClass: 'btn-blue', action: function () {
                        const value = String(this.$content.find('#fleet_washes_input').val() || '').trim();
                        if (value === '' || Number(value) < 0) return false;
                        const form = $('<form method="POST" style="display:none"></form>').attr('action', @json($carBase) + '/' + id + '/washes');
                        form.append($('<input type="hidden" name="_token">').val(@json(csrf_token())));
                        form.append($('<input type="hidden" name="washes">').val(value));
                        $('body').append(form);
                        form.trigger('submit');
                    }},
                    no: { text: I18N.no }
                }
            });
        });

        $('.fleet-driver').on('click', function () {
            const id = $(this).data('id');
            const phone = String($(this).data('phone') || '');
            $.confirm({
                title: I18N.fleet_change_driver,
                content: '<div class="form-group"><label for="fleet_driver_input">' + I18N.fleet_driver_phone + '</label>'
                    + '<input class="form-control" id="fleet_driver_input" maxlength="32" placeholder="5XX XX XX XX">'
                    + '<small class="d-block mt-1">' + I18N.fleet_change_driver_hint + '</small></div>',
                onContentReady: function () { this.$content.find('#fleet_driver_input').val(phone).trigger('focus'); },
                buttons: {
                    yes: { text: I18N.save, btnClass: 'btn-blue', action: function () {
                        const value = String(this.$content.find('#fleet_driver_input').val() || '').trim();
                        if (value === '') return false;
                        const form = $('<form method="POST" style="display:none"></form>').attr('action', @json($carBase) + '/' + id + '/driver');
                        form.append($('<input type="hidden" name="_token">').val(@json(csrf_token())));
                        form.append($('<input type="hidden" name="phone">').val(value));
                        $('body').append(form);
                        form.trigger('submit');
                    }},
                    no: { text: I18N.no }
                }
            });
        });
    </script>
@endpush
