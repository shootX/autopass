@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.add_body_type') }}</div>
            <div class="card-body table-responsive">

                <form action="{{ route('admin.body_types.store') }}" method="post" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group mt-3">
                        <label for="name">{{ __('admin.body_type_name') }}</label>
                        <input class="form-control" id="name" type="text" required name="name" maxlength="64" value="{{ old('name') }}" placeholder="{{ __('admin.enter_body_type') }}">
                    </div>

                    <div class="form-group mt-3">
                        <label for="image">{{ __('admin.photo') }}</label>
                        <input class="form-control" id="image" type="file" name="image" accept="image/jpeg,image/png,image/jpg,image/gif,image/svg+xml,image/webp">
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
