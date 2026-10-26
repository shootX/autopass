@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.packages.tabs')

        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __($package->exists ? 'admin.cp_edit' : 'admin.cp_add') }} · {{ __('admin.cp_type_'.$package->type) }}</div>
            <div class="card-body" style="max-width:720px">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ $package->exists ? route('admin.packages.corporate.save', $package) : route('admin.packages.corporate.store') }}">
                    @csrf
                    @if($package->exists)
                        <p><span class="text-muted">{{ __('admin.cp_company') }}:</span> <b>{{ $package->client?->name ?? '—' }}</b></p>
                    @else
                        <input type="hidden" name="type" value="{{ $package->type }}">
                        <label class="form-label" for="cp_company">{{ __('admin.cp_company') }}</label>
                        <select class="form-select" id="cp_company" name="corporate_client_id" required>
                            <option value="">—</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected((int) old('corporate_client_id', $package->corporate_client_id) === $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <small class="d-block text-muted mb-3">{{ __('admin.cp_company_hint') }}</small>
                    @endif

                    <label class="form-label" for="cp_title">{{ __('admin.cw_package_title') }}</label>
                    <input class="form-control mb-3" id="cp_title" name="title" required maxlength="120" value="{{ old('title', $package->title) }}">

                    @unless($package->isDiscount())
                        <label class="form-label" for="cp_washes">{{ __('admin.cw_package_washes') }}</label>
                        <input class="form-control mb-3" id="cp_washes" type="number" name="washes" required min="1" max="100000" value="{{ old('washes', $package->washes) }}">
                    @endunless

                    <label class="form-label" for="cp_price">{{ __('admin.cw_package_price') }}</label>
                    <input class="form-control" id="cp_price" type="number" name="price" min="0" step="0.01" value="{{ old('price', $package->price) }}">
                    <small class="d-block text-muted mb-3">{{ __($package->isDiscount() ? 'admin.cp_price_hint_discount' : 'admin.cp_price_hint') }}</small>

                    @if($package->isDiscount())
                        <h6 class="mt-2">{{ __('admin.cp_prices') }}</h6>
                        <small class="d-block text-muted mb-2">{{ __('admin.cp_prices_hint') }}</small>
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>{{ __('admin.body_type') }}</th>
                                <th>{{ __('admin.cd_full_price') }}, ₾</th>
                                <th>{{ __('admin.cd_percent') }}, %</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($bodyTypes as $type)
                                @php($row = $prices->get($type))
                                <tr>
                                    <td>{{ $type }}</td>
                                    <td><input class="form-control form-control-sm" type="number" name="prices[{{ $type }}][full_price]" min="0.01" max="99999" step="0.01" value="{{ old('prices.'.$type.'.full_price', $row?->full_price) }}" aria-label="{{ $type }} {{ __('admin.cd_full_price') }}"></td>
                                    <td><input class="form-control form-control-sm" type="number" name="prices[{{ $type }}][percent]" min="1" max="100" value="{{ old('prices.'.$type.'.percent', $row?->percent) }}" aria-label="{{ $type }} {{ __('admin.cd_percent') }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif

                    <label class="d-block"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active))> {{ __('admin.cw_package_active') }}</label>
                    @if($package->isDiscount())
                        <small class="d-block text-muted">{{ __('admin.cp_many_discounts') }}</small>
                        @if($package->exists && ($carCount = $package->cars()->count()) > 0)
                            <small class="d-block text-danger">{{ __('admin.cp_discount_cars', ['count' => $carCount]) }}</small>
                        @endif
                    @endif

                    <button class="btn btn-primary mt-3" type="submit">{{ __('admin.save') }}</button>
                    <a class="btn btn-link mt-3" href="{{ route('admin.packages.corporate', array_filter(['company' => $package->corporate_client_id])) }}">{{ __('admin.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
@endsection
