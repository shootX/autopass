<div class="row">
    <div class="col-lg-4 d-flex">
        <div class="card mt-4 w-100">
            <div class="card-header">{{ __('admin.fleet_manual') }}</div>
            <div class="card-body">
                @php
                    $catalog = \App\Models\CarBrand::query()->with(['models' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get();
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
                        <label for="fleet_brand">{{ __('admin.brand') }}<span class="text-danger">*</span></label>
                        <select class="form-control" id="fleet_brand" name="brand_id" required>
                            <option value="">{{ __('admin.select_brand') }}</option>
                            @foreach($catalog as $brand)
                                <option value="{{ $brand->id }}" @selected((string) old('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                        @error('brand_id')<small style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group mt-3">
                        <label for="fleet_model">{{ __('admin.model') }}<span class="text-danger">*</span></label>
                        <select class="form-control" id="fleet_model" name="model_id" required>
                            <option value="">{{ __('admin.select_model') }}</option>
                            @foreach($catalog as $brand)
                                @foreach($brand->models as $model)
                                    <option value="{{ $model->id }}" data-brand="{{ $brand->id }}" @selected((string) old('model_id') === (string) $model->id) hidden>{{ $model->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        @error('model_id')<small style="color:red;">{{ $message }}</small>@enderror
                    </div>
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
                    $token = (string) $corporate->api_token;
                    $masked = str_repeat('•', max(0, strlen($token) - 4)).substr($token, -4);
                @endphp
                <div class="mb-2"><code id="fleet-token" style="word-break:break-all;" data-full="{{ $token }}" data-masked="{{ $masked }}">{{ $masked }}</code></div>
                <button class="btn btn-outline-secondary btn-sm" type="button" id="fleet-token-toggle">{{ __('admin.fleet_token_show') }}</button>
                <form action="{{ $tokenUrl }}" method="post" class="d-inline" onsubmit="return confirm(@json(__('admin.corporate_token_confirm')))">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('admin.corporate_token_reset_btn') }}</button>
                </form>
                <pre class="mt-3 mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;">POST {{ url('/api/corporate/vehicles') }}
X-Api-Key: <span id="fleet-token-inline">{{ $masked }}</span>
{"plate":"AB123CD","brand":"Toyota","model":"Camry"}</pre>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4 mb-4">
    <div class="card-header">{{ __('admin.fleet') }} · {{ $cars->total() }}</div>
    <div class="card-body table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ __('admin.plate') }}</th>
                <th>{{ __('admin.brand') }}</th>
                <th>{{ __('admin.model') }}</th>
                <th>{{ __('admin.fleet_source') }}</th>
                <th>{{ __('admin.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($cars as $car)
                <tr>
                    <td>{{ \App\Services\FleetCars::formatPlate($car->plate) }}</td>
                    <td>{{ $car->brand }}</td>
                    <td>{{ $car->model }}</td>
                    <td>{{ __('admin.fleet_source_'.$car->source) }}</td>
                    <td><a class="btn btn-sm btn-danger remove-car" data-id="{{ $car->id }}"><i class="fas fa-trash"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="umami-empty">{{ __('admin.fleet_empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $cars->links('pagination::bootstrap-5') }}
    </div>
</div>

@push('js')
    <script>
        function fleetModels() {
            const brand = String($('#fleet_brand').val() || '');
            const select = $('#fleet_model');
            select.prop('disabled', brand === '');
            select.find('option').each(function () {
                const option = $(this);
                if (!option.val()) return;
                const show = String(option.data('brand')) === brand;
                option.prop('hidden', !show);
                option.prop('disabled', !show);
                if (!show && option.is(':selected')) option.prop('selected', false);
            });
        }
        $('#fleet_brand').on('change', fleetModels);
        fleetModels();

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
                        window.location.href = @json($deleteBase) + '/' + id + '/delete';
                    }},
                    no: { text: I18N.no }
                }
            });
        });
    </script>
@endpush
