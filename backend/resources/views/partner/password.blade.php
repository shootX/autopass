@extends('partner.layout')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.password_change_required') }}</div>
            <div class="card-body">
                <p>{{ __('admin.password_change_hint') }}</p>
                <form action="{{ route('partner.password.save') }}" method="post">
                    @csrf
                    <x-form-input required type="password" title="{{ __('admin.new_password') }}" name="password"/>
                    <x-form-input required type="password" title="{{ __('admin.password_confirm') }}" name="password_confirmation"/>
                    <button class="btn btn-primary mt-4" type="submit">{{ __('admin.save') }}</button>
                </form>
                @if($errors->any())
                    <div class="alert alert-danger mt-4">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
