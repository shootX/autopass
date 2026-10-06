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
            <div class="card-header">{{ __('admin.edit_client') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.clients.edit_save', $client->id)}}" method="post">
                    @csrf

                    <div class="form-group">
                        <label for="name" class="form-label">{{ __('admin.first_name') }}</label>
                        <input type="text" class="form-control" id="name" name="name" value="{{old('name', $client->name)}}" required>
                    </div>
                    <div class="form-group mt-3">
                        <label for="surname" class="form-label">{{ __('admin.last_name') }}</label>
                        <input type="text" class="form-control" id="surname" name="surname" value="{{old('surname', $client->surname)}}" required>
                    </div>
                    <div class="form-group mt-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="{{old('email', $client->email)}}" required>
                    </div>
                    <div class="form-group mt-3">
                        <label for="phone" class="form-label">{{ __('admin.phone') }}</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', \App\Support\Phone::format($client->phone)) }}" placeholder="599 12 34 56" required>
                    </div>
                    <div class="form-group mt-3">
                        <label for="sex" class="form-label">{{ __('admin.sex') }}</label>
                        <select id="sex" class="form-select" name="sex">
                            <option @if($client->sex == "male") selected @endif value="male">{{ __('admin.male') }}</option>
                            <option @if($client->sex == "female") selected @endif value="female">{{ __('admin.female') }}</option>
                        </select>
                    </div>
                    <div class="form-group mt-3">
                        <label for="date_of_birth" class="form-label">{{ __('admin.birth_date') }}</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="{{old('date_of_birth', \Carbon\Carbon::parse($client->date_of_birth)->format('Y-m-d'))}}" required>
                    </div>

                    <div class="form-group mt-5">
                        <label for="password" class="form-label">{{ __('admin.new_password') }}</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="{{ __('admin.new_password_placeholder') }}">
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
