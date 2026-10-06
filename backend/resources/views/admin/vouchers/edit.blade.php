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
            <div class="card-header">{{ __('admin.edit_voucher') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.vouchers.save', $voucher->id)}}" enctype="multipart/form-data" method="post">
                    @csrf


                    <div class="form-group">
                        <label for="carType">{{ __('admin.category') }}</label>
                        <select id="carType" name="category_id" class="form-select">
                            @if(count($categories) > 0)
                                @foreach($categories as $categorie)
                                    <option @if($categorie->id == $voucher->category_id) selected @endif value="{{$categorie->id}}">{{$categorie->name}}</option>
                                @endforeach
                            @else
                                <option disabled selected>{{ __('admin.no_categories') }}</option>
                            @endif
                        </select>
                    </div>

                    <x-form-input value="{{old('name', $voucher->name)}}" required type="text" title="{{ __('admin.name') }}" name="name"/>
                    <x-form-input value="{{old('description', $voucher->description)}}" required type="textarea" rows="5" title="{{ __('admin.description') }}" name="description"/>

                    <div class="form-group mt-3">
                        <label for="carType">{{ __('admin.photo') }}</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpg, image/jpeg, image/png">
                    </div>

                    <x-form-input value="{{old('price', $voucher->price)}}" required type="number" title="{{ __('admin.cost') }}" name="price"/>

                    <div class="card mt-4 mb-4">
                        <div class="card-header">{{ __('admin.voucher_params') }}</div>
                        <div class="card-body">

                            <div class="form-group mt-3">
                                <label for="carType">{{ __('admin.voucher_type') }}</label>
                                <select name="voucher_type" class="form-select" id="voucherType">
                                    <option @if($voucher->percent != null) selected @endif value="1">{{ __('admin.percent_type') }}</option>
                                    <option @if($voucher->percent == null) selected @endif value="2">{{ __('admin.custom') }}</option>
                                </select>
                            </div>

                            <x-form-input type="number" title="{{ __('admin.percent') }}" name="percent"/>

                            <x-form-input type="textarea" title="{{ __('admin.conditions') }}" name="conditions"/>

                        </div>
                    </div>



                    <x-form-input value="{{old('count', $voucher->count)}}" type="number" title="{{ __('admin.quantity') }}" placeholder="{{ __('admin.unlimited_hint') }}" name="count"/>


                    <x-form-input value="{{old('footer_text', $voucher->footer_text)}}" required rows="5" type="textarea" title="{{ __('admin.footer_left') }}" name="footer_text"/>
                    <x-form-input value="{{old('footer_geo', $voucher->footer_geo)}}" required type="text" title="{{ __('admin.maps_link') }}" name="footer_geo"/>



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

@push('js')
    <script>
        $(document).ready(function(){
            let voucher = {!! $voucher !!};
            $("#conditions_field_id").parent().hide();
            $("#voucherType").change(function(){
                let val = $(this).val();
                if(val == '1')
                {
                    $("#conditions_field_id").val('');
                    $("#conditions_field_id").parent().hide();

                    $("#percent_field_id").val(voucher.percent);
                    $("#percent_field_id").parent().show();
                }
                if(val == '2')
                {
                    $("#conditions_field_id").val(voucher.conditions);
                    $("#conditions_field_id").parent().show();

                    $("#percent_field_id").val('');
                    $("#percent_field_id").parent().hide();
                }
            });

            $("#voucherType").trigger('change');
            if(voucher.percent === null)
            {
                $('#conditions_field_id').val(voucher.conditions);
            }
        });

    </script>
@endpush
