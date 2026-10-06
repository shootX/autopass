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
            <div class="card-header">{{ __('admin.edit_package') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.packages.edit_save', $package->id)}}" method="post">
                    @csrf
                    <div class="form-group">
                        <label for="carType">{{ __('admin.body_type') }}</label>
                        <select id="carType" name="car_type" class="form-select">
                            @if(count($carTypes) > 0)
                                @foreach($carTypes as $type)
                                    <option @if($package->car_type == $type) selected @endif >{{$type}}</option>
                                @endforeach
                            @else
                                <option disabled selected>{{ __('admin.no_body_types') }}</option>
                            @endif
                        </select>
                    </div>

                    @if(count($carTypes) > 0)

                        <div class="form-group mt-3">
                            <label for="count_washes">{{ __('admin.wash_count') }}</label>
                            <input class="form-control" id="count_washes" type="number" required name="count_washes" value="{{$package->count_washes ?? old('count_washes')}}">
                        </div>

                        <div class="form-group mt-3">
                            <label for="price1">{{ __('admin.price_1_label') }}</label>
                            <input class="form-control" id="price1" type="number" required name="price1" value="{{ old('price1', $package->priceForMonth(1)?->price ?? 0) }}">
                        </div>

                        <div class="form-group mt-3">
                            <label for="price2">{{ __('admin.price_3_label') }}</label>
                            <input class="form-control" id="price2" type="number" required name="price2" value="{{ old('price2', $package->priceForMonth(3)?->price ?? 0) }}">
                        </div>

                        <div class="form-group mt-3">
                            <label for="price3">{{ __('admin.price_6_label') }}</label>
                            <input class="form-control" id="price3" type="number" required name="price3" value="{{ old('price3', $package->priceForMonth(6)?->price ?? 0) }}">
                        </div>

                        <div class="form-group mt-3">
                            <label for="price4">{{ __('admin.price_12_label') }}</label>
                            <input class="form-control" id="price4" type="number" required name="price4" value="{{ old('price4', $package->priceForMonth(12)?->price ?? 0) }}">
                        </div>

                    @endif


                    <button @if(count($carTypes) <= 0) disabled @endif type="submit" class="btn btn-primary mt-4">{{ __('admin.save') }}</button>


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
