@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.partials.alerts')
        <div class="card mt-4 mb-4">
            <div class="card-header d-flex justify-content-between">
                <div>{{ __('admin.inv_settings') }}</div>
                <a href="{{ route('admin.corporate.invoices') }}" class="btn btn-sm btn-outline-secondary">{{ __('admin.inv_title') }}</a>
            </div>
            <div class="card-body">
                <p class="text-muted">{{ __('admin.inv_settings_hint') }}</p>
                <form action="{{ route('admin.corporate.invoices.settings.save') }}" method="post" class="row">
                    @csrf
                    @foreach($fields as $field)
                        <div class="col-md-4"><x-form-input type="text" name="{{ $field }}" title="{{ __('admin.inv_seller_'.$field) }}" value="{{ $config['seller'][$field] ?? '' }}"/></div>
                    @endforeach
                    <div class="col-md-4"><x-form-input type="text" name="prefix" title="{{ __('admin.inv_prefix') }}" value="{{ $config['prefix'] }}"/></div>
                    <div class="col-md-4"><x-form-input type="number" name="due_days" title="{{ __('admin.inv_due_days') }}" value="{{ $config['due_days'] }}"/></div>
                    <div class="col-md-4"><x-form-input type="number" name="vat_rate" title="{{ __('admin.inv_vat_rate') }}" value="{{ $config['vat_rate'] }}"/></div>
                    <div class="col-12"><button class="btn btn-primary mt-3">{{ __('admin.save') }}</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
