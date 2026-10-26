@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.add_body_type') }}</div>
            <div class="card-body table-responsive">

                <form action="{{ route('admin.body_types.store') }}" method="post">
                    @csrf

                    <div class="form-group mt-3">
                        <label for="name_ka">{{ __('admin.body_type_name_ka') }}</label>
                        <input class="form-control" id="name_ka" type="text" required name="name_ka" maxlength="64" value="{{ old('name_ka') }}">
                    </div>

                    <div class="form-group mt-3">
                        <label for="name_en">{{ __('admin.body_type_name_en') }}</label>
                        <input class="form-control" id="name_en" type="text" required name="name_en" maxlength="64" value="{{ old('name_en') }}">
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">{{ __('admin.save') }}</button>

                    @if($errors->any())
                        <div class="alert alert-danger mt-4">
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
@endsection
