@extends('partner.layout')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.partner_account') }}</div>
            <div class="card-body">
                @if(session()->has('message'))
                    <div class="alert alert-success">{{ session()->get('message') }}</div>
                @endif
                <form action="{{ route('partner.account.save') }}" method="post">
                    @csrf
                    <x-form-input required type="text" title="{{ __('admin.username') }}" name="username" :value="$corporate->username"/>
                    <x-form-input required type="password" title="{{ __('admin.current_password') }}" name="current_password"/>
                    <x-form-input type="password" title="{{ __('admin.new_password') }}" name="password"/>
                    <x-form-input type="password" title="{{ __('admin.password_confirm') }}" name="password_confirmation"/>
                    <button class="btn btn-primary mt-4" type="submit">{{ __('admin.save') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
