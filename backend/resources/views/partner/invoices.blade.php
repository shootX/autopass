@extends('partner.layout')

@section('content')
    <div class="container-fluid px-4">
        @if(session()->has('message'))
            <div class="alert alert-success mt-4">{{ session()->get('message') }}</div>
        @endif
        @include('admin.corporate.invoices')
    </div>
@endsection
