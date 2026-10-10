@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @if(session()->has('message'))
            <div class="alert alert-success mt-4">{{ session()->get('message') }}</div>
        @endif

        <div class="card mt-4">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div>{{ $corporate->name }}</div>
                <a class="btn btn-sm btn-primary" href="{{ route('admin.corporate.edit', $corporate) }}">{{ __('admin.edit') }}</a>
            </div>
            <div class="card-body">
                <div>{{ __('admin.legal_form') }}: {{ $corporate->legal_form }}</div>
                <div>{{ __('admin.identification_code') }}: {{ $corporate->identification_code }}</div>
                <div>{{ __('admin.legal_address') }}: {{ $corporate->legal_address }}</div>
                <div>{{ __('admin.actual_address') }}: {{ $corporate->actual_address }}</div>
                <div>{{ __('admin.bank_name') }}: {{ $corporate->bank_name }}, {{ $corporate->bank_code }}, {{ $corporate->bank_account }}</div>
                <div>{{ __('admin.contact') }}: {{ $corporate->contact }}</div>
                <div>{{ __('admin.phone') }}: {{ $corporate->phone }}</div>
                <div>{{ __('admin.email') }}: {{ $corporate->email }}</div>
                <div>{{ __('admin.website') }}: {{ $corporate->website ?: '—' }}</div>
                <div>{{ __('admin.vat_payer') }}: {{ $corporate->vat_payer ? __('admin.vat_yes') : __('admin.vat_no') }}</div>
                <div class="mt-2">{{ __('admin.partner_login_user') }}: {{ $corporate->username }}</div>
                <div><a href="{{ route('partner.login') }}">{{ url('/partner/login') }}</a></div>
                <form class="mt-3" action="{{ route('admin.corporate.temporary_password', $corporate) }}" method="post">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('admin.temp_password_sent') }}</button>
                </form>
            </div>
        </div>

        @include('admin.corporate.fleet', [
            'storeUrl' => route('admin.corporate.cars.store', $corporate),
            'importUrl' => route('admin.corporate.import', $corporate),
            'templateUrl' => route('admin.corporate.template'),
            'tokenUrl' => route('admin.corporate.token', $corporate),
            'deleteBase' => url('/dashboard/corporate/'.$corporate->id.'/cars'),
        ])
    </div>
@endsection
