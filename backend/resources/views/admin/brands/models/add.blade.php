@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.add_model_for', ['name' => $brand->name]) }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.car_brand_models_store', $brand->id)}}" method="post">
                    @csrf

                    <div class="form-group mt-3">
                        <label for="name">{{ __('admin.model_name') }}</label>
                        <input class="form-control" id="name" type="text" required name="name" placeholder="{{ __('admin.enter_model') }}">
                    </div>

                    <div class="form-group mt-3">
                        <label for="type">{{ __('admin.body_type') }}</label>
                        <select class="form-select" name="type">
                            @foreach(\App\Models\BodyType::query()->orderBy('name')->pluck('name') as $type)
                                <option value="{{$type}}">{{$type}}</option>
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
