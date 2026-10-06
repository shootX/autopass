@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.add_package_for', ['name' => trim($client->name.' '.$client->surname)]) }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.clients.store_package', $client->id)}}" method="post">
                    @csrf

                    <div class="form-group mt-3">
                        <label for="selectCar">{{ __('admin.car') }}</label>
                        <select class="form-control" id="selectCar" name="car_id">
                            <option selected disabled>{{ __('admin.select_car') }}</option>
                            @foreach($client->cars as $car)
                                <option value="{{$car->id}}">{{$car->model->brand->name}} {{$car->model->name}} ({{$car->plate}})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mt-3">
                        <label for="selectPrices">{{ __('admin.packages') }}</label>
                        <select class="form-control" id="selectPrices" name="price_id">
                            <option selected disabled>{{ __('admin.select_package') }}</option>
                        </select>



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

        $("#selectCar").change(function () {
           let carID = $(this).val();
           if (carID) {
               $.get('/dashboard/clients/get_packages_for_car/'+carID, function (data) {

                    $("#selectPrices").html(data);

               });
           }
        });

        $('#selectClient').select2({
            theme: 'bootstrap-5',
            placeholder: I18N.type_to_search,
            minimumInputLength: 3, // начинать поиск с 2 символов
            dropdownParent: $('.card-body'),
            ajax: {
                url: '/dashboard/search_clients',
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

@endpush
