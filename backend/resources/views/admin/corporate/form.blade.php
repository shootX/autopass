@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ $client ? __('admin.edit_corporate') : __('admin.add_corporate') }}</div>
            <div class="card-body">
                <form action="{{ $client ? route('admin.corporate.save', $client) : route('admin.corporate.store') }}" method="post">
                    @csrf
                    <x-form-input required type="text" title="{{ __('admin.company') }}" name="name" :value="$client->name ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.legal_form') }}" name="legal_form" :value="$client->legal_form ?? null" small="{{ __('admin.legal_form_hint') }}"/>
                    <x-form-input required type="text" title="{{ __('admin.identification_code') }}" name="identification_code" :value="$client->identification_code ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.legal_address') }}" name="legal_address" :value="$client->legal_address ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.actual_address') }}" name="actual_address" :value="$client->actual_address ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.bank_name') }}" name="bank_name" :value="$client->bank_name ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.bank_code') }}" name="bank_code" :value="$client->bank_code ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.bank_account') }}" name="bank_account" :value="$client->bank_account ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.contact') }}" name="contact" :value="$client->contact ?? null"/>
                    <x-form-input required type="text" title="{{ __('admin.phone') }}" name="phone" :value="$client->phone ?? null"/>
                    <x-form-input required type="email" title="{{ __('admin.email') }}" name="email" :value="$client->email ?? null"/>
                    <x-form-input type="url" title="{{ __('admin.website') }}" name="website" :value="$client->website ?? null"/>
                    <div class="form-group mt-3">
                        <label for="vat_payer_field">{{ __('admin.vat_payer') }}<span class="text-danger">*</span></label>
                        <select class="form-control" id="vat_payer_field" name="vat_payer" required>
                            <option value="" disabled @selected(old('vat_payer', $client ? null : '') === '' && !$client)>{{ __('admin.vat_payer') }}</option>
                            <option value="1" @selected((string) old('vat_payer', $client ? (int) $client->vat_payer : '') === '1')>{{ __('admin.vat_yes') }}</option>
                            <option value="0" @selected((string) old('vat_payer', $client ? (int) $client->vat_payer : '') === '0')>{{ __('admin.vat_no') }}</option>
                        </select>
                        @error('vat_payer')<small style="color:red;">{{ $message }}</small>@enderror
                    </div>
                    @unless($client)
                        <small class="d-block mt-3">{{ __('admin.partner_initial_login') }}</small>
                    @endunless
                    <button type="submit" class="btn btn-primary mt-4">{{ __('admin.save') }}</button>
                    <a href="{{ $client ? route('admin.corporate.show', $client) : route('admin.corporate') }}" class="btn btn-outline-secondary mt-4">{{ __('admin.back') }}</a>
                </form>
            </div>
        </div>
    </div>
@endsection
