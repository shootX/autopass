@extends('admin.layout.app')

@push('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map {
            height: 400px;
            width: 100%;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.edit_washing') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.washings.edit_save', ['washing' => $washing->id])}}" method="post">
                    @csrf
                    <div class="form-group">
                        <label for="name">{{ __('admin.name') }}</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="{{ __('admin.enter_name') }}" value="{{old('name') ?? $washing->name}}">
                    </div>
                    <div class="form-group">
                        <label for="address">{{ __('admin.address') }}</label>
                        <input type="text" class="form-control" id="address" name="address" placeholder="{{ __('admin.enter_address') }}" value="{{old('address') ?? $washing->address}}">
                    </div>
                    <div class="form-group">
                        <label for="work_time_start">{{ __('admin.work_start') }}</label>
                        <input type="time" class="form-control" id="work_time_start" name="work_time_start" placeholder="{{ __('admin.enter_work_time') }}" value="{{old('work_time_start') ?? $washing->work_time_start}}">
                    </div>
                    <div class="form-group">
                        <label for="work_time_end">{{ __('admin.work_end') }}</label>
                        <input type="time" class="form-control" id="work_time_end" name="work_time_end" placeholder="{{ __('admin.enter_work_time') }}" value="{{old('work_time_end') ?? $washing->work_time_end}}">
                    </div>
                    <div class="form-group">
                        <label for="selectManager">{{ __('admin.choose_manager') }}</label>
                        <select class="form-control" id="selectManager" name="manager_id">
                            <option value="{{old('manager_id') ?? $washing->manager_id}}" selected>{{$washing->manager->name}} {{$washing->manager->surname}} ({{$washing->manager->email}})</option>
                        </select>
                    </div>
                    <div class="form-group mt-4">
                        <input type="hidden" class="form-control" id="geolocation" name="location" placeholder="{{ __('admin.enter_location') }}" value="{{old('location') ?? $washing->location}}">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#mapModal">
                            <i class="fa fa-map-marker"></i> {{ __('admin.set_location') }}
                        </button>
                    </div>

                    <div class="form-group mt-4">
                        <label for="description">{{ __('admin.services_offered') }}</label>
                        <select class="form-select" id="services" name="services[]" multiple>
                            @foreach($services as $service)
                                <option @if(in_array($service->id, $selectedServices)) selected @endif value="{{$service->id}}">{{$service->name}}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">{{ __('admin.save') }}</button>
                    @if($errors->any())
                        <div class="alert alert-danger mt-4">
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{$error}}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="mapModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('admin.pick_map_point') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="map"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="saveMarker" class="btn btn-success" data-bs-dismiss="modal">{{ __('admin.save') }}</button>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('js')
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        $('#setManagerModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var recipient = button.data('id');
            $("#washing_id").val(recipient);
        });

        $('#services').select2({
            placeholder: I18N.choose_services
        });

        $('#selectManager').select2({
            theme: 'bootstrap-5',
            placeholder: I18N.type_to_search,
            minimumInputLength: 3, // начинать поиск с 2 символов
            dropdownParent: $('.card-body'),
            ajax: {
                url: '/dashboard/search_managers',
                dataType: 'json',
                delay: 250,
                minimumInputLength: 3,
                data: function (params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            },
            placeholder: I18N.select_value,
            minimumInputLength: 2
        });
    </script>
    <script>
        let map, marker;
        const modal = document.getElementById('mapModal');

        // Когда модал открывается
        modal.addEventListener('shown.bs.modal', function () {
            if (!map) {
                // Инициализация карты
                map = L.map('map').setView([50.45, 30.52], 13); // Киев по умолчанию

                // Подложка карты
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                // Клик по карте
                map.on('click', function (e) {
                    if (marker) {
                        marker.setLatLng(e.latlng);
                    } else {
                        marker = L.marker(e.latlng).addTo(map);
                    }
                });
            }
            setTimeout(() => map.invalidateSize(), 300); // фиксим отображение в модалке
        });

        document.getElementById('saveMarker').addEventListener('click', function () {
            if (marker) {
                const coords = marker.getLatLng();
                console.log("Выбранные координаты:", coords.lat, coords.lng);
                $('#geolocation').val(coords.lat + ',' + coords.lng);
            }
        });
    </script>
@endpush
