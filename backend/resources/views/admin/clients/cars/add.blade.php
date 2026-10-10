@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            @php($who = $client->titleName())
            <div class="card-header">{{ $who !== '' ? __('admin.add_car_for', ['name' => $who]) : __('admin.add_vehicle') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.clients.store_car', $client->id)}}" method="post">
                    @csrf

                    <div class="form-group mt-3">
                        <label for="selectCar">{{ __('admin.brand') }}</label>
                        <select class="form-control" id="selectBrand" name="brand_id">
                            <option selected disabled>{{ __('admin.select_brand') }}</option>
                        </select>
                    </div>

                    <div class="form-group mt-3">
                        <label for="selectCar">{{ __('admin.model') }}</label>
                        <select class="form-control" id="selectModel" name="model_id">
                            <option selected disabled>{{ __('admin.select_model') }}</option>
                        </select>
                    </div>

                    <div class="form-group mt-3">
                        <label for="plate">{{ __('admin.plate') }}</label>
                        <input class="form-control" id="plate" type="text" required name="plate" placeholder="{{ __('admin.enter_plate') }}">
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

@push('js')
    <script>

        $('#selectBrand').select2({
            theme: 'bootstrap-5',
            placeholder: I18N.type_to_search,
            minimumInputLength: 3, // начинать поиск с 2 символов
            dropdownParent: $('.card-body'),
            ajax: {
                url: '/dashboard/search_car_brands',
                dataType: 'json',
                delay: 250,
                minimumInputLength: 1,
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
            minimumInputLength: 1
        });

        $('#selectModel').select2({
            theme: 'bootstrap-5',
            placeholder: I18N.select_brand_first
        });

        $('#selectBrand').on('change', function () {
            let parentId = $(this).val();

            // очищаем второй select
            $('#selectModel').empty().trigger('change');

            // переинициализация второго select с новым API
            $('#selectModel').select2({
                theme: 'bootstrap-5',
                placeholder: I18N.select_model,
                ajax: {
                    url: '/dashboard/search_car_models',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            brand_id: parentId
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.items
                        };
                    }
                }
            });
        });
    </script>

@endpush
