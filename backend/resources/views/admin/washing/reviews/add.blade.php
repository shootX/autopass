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
            <div class="card-header">{{ __('admin.add_review_for', ['washing' => $washing->name.' ('.$washing->address.')']) }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.washings.store_review', $washing->id)}}" method="post">
                    @csrf

                    <div class="form-group">
                        <label for="selectUser">{{ __('admin.user') }}</label>
                        <select class="form-control" id="selectUser" name="user_id"></select>
                    </div>

                    <div class="form-group mt-3">
                        <label for="stars">{{ __('admin.rating') }}</label>
                        <select class="form-control" id="stars" name="stars">
                            <option value="1">{{ __('admin.star_1') }}</option>
                            <option value="2">{{ __('admin.star_2') }}</option>
                            <option value="3">{{ __('admin.star_3') }}</option>
                            <option value="4">{{ __('admin.star_4') }}</option>
                            <option value="5">{{ __('admin.star_5') }}</option>
                        </select>
                    </div>

                    <div class="form-group mt-3">
                        <label for="text">{{ __('admin.review_text') }}</label>
                        <textarea class="form-control" id="text" name="text" rows="5" placeholder="{{ __('admin.enter_review') }}" required></textarea>
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
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>

        $('#selectUser').select2({
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
            minimumInputLength: 3
        });

    </script>

@endpush
