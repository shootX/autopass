@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.site._tabs')

        <form action="{{ route('admin.site.settings.save') }}" method="post" class="card mt-4 mb-4">
            @csrf
            <div class="card-body">
                <h6>{{ __('admin.site_prices') }}</h6>
                <p class="text-muted small">{{ __('admin.site_prices_hint') }}</p>
                <div class="row g-3">
                    @foreach([4, 8, 12] as $n)
                        <div class="col-md-3">
                            <label for="price_{{ $n }}">{{ __('admin.site_price_for', ['n' => $n]) }}</label>
                            <input class="form-control" id="price_{{ $n }}" type="number" step="0.01" min="0" required name="price_{{ $n }}" value="{{ old('price_'.$n, $settings['price_'.$n]) }}">
                        </div>
                    @endforeach
                    <div class="col-md-3">
                        <label for="hours">{{ __('admin.site_hours') }}</label>
                        <input class="form-control" id="hours" type="text" maxlength="40" name="hours" value="{{ old('hours', $settings['hours']) }}">
                    </div>
                </div>

                <div class="form-check mt-4">
                    <input type="hidden" name="branches_from_db" value="0">
                    <input class="form-check-input" type="checkbox" id="branches_from_db" name="branches_from_db" value="1" @checked(old('branches_from_db', $settings['branches_from_db']) === '1')>
                    <label class="form-check-label" for="branches_from_db">{{ __('admin.site_branches_from_db') }}</label>
                </div>

                <h6 class="mt-4">{{ __('admin.site_contacts') }}</h6>
                <div class="row g-3">
                    @foreach(['phone' => 'text', 'email' => 'email', 'ios_url' => 'url', 'privacy_url' => 'url', 'terms_url' => 'url'] as $field => $type)
                        <div class="col-md-6">
                            <label for="{{ $field }}">{{ __('admin.site_'.$field) }}</label>
                            <input class="form-control" id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field, $settings[$field]) }}">
                        </div>
                    @endforeach
                </div>
                <p class="text-muted small mt-2">{{ __('admin.site_contacts_hint') }}</p>

                <button type="submit" class="btn btn-primary mt-3">{{ __('admin.save') }}</button>
            </div>
        </form>
    </div>
@endsection
