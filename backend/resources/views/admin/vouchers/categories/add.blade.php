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
            <div class="card-header">{{ __('admin.add_category') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.vouchers.categories.store')}}" method="post">
                    @csrf

                    <x-form-input required type="text" title="{{ __('admin.name') }}" name="name"/>

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
