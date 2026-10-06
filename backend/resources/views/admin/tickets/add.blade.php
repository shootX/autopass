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
            <div class="card-header">{{ __('admin.add_ticket') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.tickets.store')}}" enctype="multipart/form-data" method="post">
                    @csrf

                    <x-form-input required type="text" title="{{ __('admin.name') }}" name="name"/>
                    <x-form-input required type="textarea" rows="5" title="{{ __('admin.description') }}" name="description"/>

                    <div class="form-group mt-3">
                        <label for="carType">{{ __('admin.photo') }}</label>
                        <input required type="file" name="photo" class="form-control" accept="image/jpg, image/jpeg, image/png">
                    </div>

                    <x-form-input required type="number" title="{{ __('admin.cost') }}" name="price"/>
                    <x-form-input required type="date" title="{{ __('admin.date_until') }}" name="date_to"/>
                    <x-form-input type="number" title="{{ __('admin.quantity') }}" placeholder="{{ __('admin.unlimited_hint') }}" name="count"/>

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
