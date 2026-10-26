@extends('partner.layout')

@section('content')
    <div class="container-fluid px-4">
        @if(session()->has('message'))
            <div class="alert alert-success mt-4">{{ session()->get('message') }}</div>
        @endif
        @include('admin.corporate.fleet', [
            'storeUrl' => route('partner.cars.store'),
            'importUrl' => route('partner.import'),
            'templateUrl' => route('partner.template'),
            'tokenUrl' => route('partner.token'),
            'catalogUrl' => url('/partner/catalog'),
            'carBase' => url('/partner/cars'),
        ])
    </div>
@endsection
