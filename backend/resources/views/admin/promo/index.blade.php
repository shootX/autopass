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
            <div class="card-header">{{ __('admin.promo') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.promo.save')}}" method="post">
                    @csrf

                    <x-form-input required type="text" value="{{$promo->title ?? ''}}" title="{{ __('admin.title') }}" name="title"/>
                    <x-form-input required type="text" value="{{$promo->description ?? ''}}" title="{{ __('admin.short_text') }}" name="description"/>
                    <x-form-input required type="text" value="{{$promo->url ?? ''}}" title="{{ __('admin.link') }}" name="url"/>

                    <button type="submit" name="remove" class="btn btn-danger mt-4">{{ __('admin.remove_banner') }}</button>
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
