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
            <div class="card-header">{{ __('admin.edit_partner') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.partners.edit_save', $client->id)}}" method="post">
                    @csrf

                    <x-form-input value="{{old('name', $client->name)}}" required type="text" title="{{ __('admin.name') }}" name="name"/>
                    <x-form-input value="{{old('description', $client->description)}}" required type="textarea" rows="5" title="{{ __('admin.description') }}" name="description"/>

                    <x-form-input value="{{old('login', $client->login)}}" required type="text" title="{{ __('admin.login_name') }}" name="login"/>
                    <x-form-input value="{{old('email', $client->email)}}" type="email" title="{{ __('admin.email') }}" name="email"/>

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
                <form action="{{ route('admin.partners.temporary_password', $client) }}" method="post" class="mt-3">
                    @csrf
                    <button class="btn btn-outline-secondary" type="submit">{{ __('admin.temp_password_sent') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
