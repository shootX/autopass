@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.add_brand') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.store_car_brand')}}" method="post">
                    @csrf

                    <div class="form-group mt-3">
                        <label for="name">{{ __('admin.brand_name') }}</label>
                        <input class="form-control" id="name" type="text" required name="name" placeholder="{{ __('admin.enter_brand') }}">
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
