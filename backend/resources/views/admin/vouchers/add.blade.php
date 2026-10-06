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
            <div class="card-header">{{ __('admin.add_voucher') }}</div>
            <div class="card-body">

                <form action="{{route('admin.store_vouchers')}}" enctype="multipart/form-data" method="post">
                    @csrf

                    <div class="form-group">
                        <label for="carType">{{ __('admin.category') }}</label>
                        <select id="carType" name="category_id" class="form-select">
                            @if(count($categories) > 0)
                                @foreach($categories as $categorie)
                                    <option value="{{$categorie->id}}">{{$categorie->name}}</option>
                                @endforeach
                            @else
                                <option disabled selected>{{ __('admin.no_categories') }}</option>
                            @endif
                        </select>
                    </div>

                    <x-form-input required type="text" title="{{ __('admin.name') }}" name="name"/>
                    <x-form-input required type="textarea" rows="5" title="{{ __('admin.description') }}" name="description"/>

                    <div class="form-group mt-3">
                        <label for="carType">{{ __('admin.photo') }}</label>
                        <input required type="file" name="photo" class="form-control" accept="image/jpg, image/jpeg, image/png">
                    </div>

                    <x-form-input required type="number" title="{{ __('admin.cost') }}" name="price"/>

                    <div class="card mt-4 mb-4">
                        <div class="card-header">{{ __('admin.voucher_params') }}</div>
                        <div class="card-body">

                            <div class="form-group mt-3">
                                <label for="carType">{{ __('admin.voucher_type') }}</label>
                                <select name="voucher_type" class="form-select" id="voucherType">
                                    <option value="1">{{ __('admin.percent_type') }}</option>
                                    <option value="2">{{ __('admin.custom') }}</option>
                                </select>
                            </div>

                            <x-form-input type="number" title="{{ __('admin.percent') }}" name="percent"/>

                            <x-form-input type="textarea" title="{{ __('admin.conditions') }}" name="conditions"/>

                        </div>
                    </div>



                    <x-form-input type="number" title="{{ __('admin.quantity') }}" placeholder="{{ __('admin.unlimited_hint') }}" name="count"/>


                    <x-form-input required rows="5" type="textarea" title="{{ __('admin.footer_left') }}" name="footer_text"/>
                    <x-form-input required type="text" title="{{ __('admin.maps_link') }}" name="footer_geo"/>

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
        $(document).ready(function(){
            $("#conditions_field_id").parent().hide();
            $("#voucherType").change(function(){
               let val = $(this).val();
               if(val == '1')
               {
                   $("#conditions_field_id").val('');
                   $("#conditions_field_id").parent().hide();

                   $("#percent_field_id").val('');
                   $("#percent_field_id").parent().show();
               }
               if(val == '2')
               {
                   $("#conditions_field_id").val('');
                   $("#conditions_field_id").parent().show();

                   $("#percent_field_id").val('');
                   $("#percent_field_id").parent().hide();
               }
            });
        });
    </script>
@endpush
