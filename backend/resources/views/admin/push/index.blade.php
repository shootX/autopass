@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.partials.alerts')
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.push_title') }}</div>
            <div class="card-body">
                <p class="text-muted">{{ __('admin.push_audience', ['count' => $audience]) }}</p>
                <form action="{{ route('admin.push.send') }}" method="post" onsubmit="return confirm(I18N.push_send_confirm)">
                    @csrf
                    <div class="row">
                        @foreach(['ka' => true, 'en' => false, 'ru' => false] as $lang => $required)
                            <div class="col-md-4">
                                <h6 class="mt-3 text-uppercase">{{ $lang }}</h6>
                                <x-form-input :required="$required" type="text" name="title_{{ $lang }}" title="{{ __('admin.push_heading') }}"/>
                                <x-form-input :required="$required" type="textarea" rows="3" name="text_{{ $lang }}" title="{{ __('admin.push_text') }}"/>
                            </div>
                        @endforeach
                    </div>
                    <x-form-input type="url" name="url" title="{{ __('admin.link') }}" small="{{ __('admin.push_lang_hint') }}"/>
                    <button class="btn btn-primary mt-3"><i class="fas fa-paper-plane"></i> {{ __('admin.push_send') }}</button>
                </form>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">{{ __('admin.apns_title') }}
                <span class="badge {{ $configured ? 'bg-success' : 'bg-danger' }} ms-2">{{ $configured ? __('admin.apns_ready') : __('admin.apns_missing') }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted">{{ __('admin.apns_hint') }}</p>
                <form action="{{ route('admin.push.settings') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-4"><x-form-input required type="text" name="key_id" title="Key ID" :value="old('key_id', $apns['key_id'])"/></div>
                        <div class="col-md-4"><x-form-input required type="text" name="team_id" title="Team ID" :value="old('team_id', $apns['team_id'])"/></div>
                        <div class="col-md-4"><x-form-input required type="text" name="bundle_id" title="Bundle ID" :value="old('bundle_id', $apns['bundle_id'])"/></div>
                    </div>
                    <x-form-input type="textarea" rows="5" name="key" title="{{ __('admin.apns_key') }}" small="{{ $apns['has_key'] ? __('admin.apns_key_kept') : __('admin.apns_key_hint') }}"/>
                    <button class="btn btn-primary mt-3"><i class="fas fa-save"></i> {{ __('admin.save') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
