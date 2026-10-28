@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.partials.alerts')

        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.points_price') }}</div>
            <div class="card-body">
                <p class="text-muted mb-0">{{ __('admin.points_price_hint', ['price' => number_format($card['point_price_final'], 2)]) }}</p>
                <form action="{{ route('admin.points.save') }}" method="post" class="row">
                    @csrf
                    <div class="col-md-4"><x-form-input required type="number" name="point_price" title="{{ __('admin.points_price_gel') }}" value="{{ $settings['point_price'] }}" small="{{ __('admin.points_price_gel_hint') }}"/></div>
                    <div class="col-md-4"><x-form-input required type="number" name="point_discount" title="{{ __('admin.points_discount') }}" value="{{ $settings['point_discount'] }}" small="{{ __('admin.points_discount_hint') }}"/></div>
                    <div class="col-md-2"><x-form-input required type="number" name="points_min" title="{{ __('admin.points_min') }}" value="{{ $settings['points_min'] }}"/></div>
                    <div class="col-md-2"><x-form-input required type="number" name="points_max" title="{{ __('admin.points_max') }}" value="{{ $settings['points_max'] }}"/></div>
                    <div class="col-md-4"><x-form-input required type="number" name="referral_percent" title="{{ __('admin.referral_percent') }}" value="{{ $settings['referral_percent'] }}" small="{{ __('admin.referral_percent_hint') }}"/></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary mt-3">{{ __('admin.save') }}</button></div>
                </form>
                <script>document.querySelector('[name=point_price]').step = '0.01';</script>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">{{ __('admin.promo_codes') }}</div>
            <div class="card-body table-responsive">
                <form action="{{ route('admin.promo_codes.store') }}" method="post" class="row align-items-end">
                    @csrf
                    <div class="col-md-2"><x-form-input required type="text" name="code" title="{{ __('admin.promo_code') }}"/></div>
                    <div class="col-md-2 form-group mt-3">
                        <label for="kind_field_id">{{ __('admin.promo_kind') }}</label>
                        <select name="kind" id="kind_field_id" class="form-select">
                            <option value="discount" @selected(old('kind') === 'discount')>{{ __('admin.promo_kind_discount') }}</option>
                            <option value="points" @selected(old('kind') === 'points')>{{ __('admin.promo_kind_points') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><x-form-input required type="number" name="value" title="{{ __('admin.promo_value') }}" small="{{ __('admin.promo_value_hint') }}"/></div>
                    <div class="col-md-2"><x-form-input type="number" name="max_uses" title="{{ __('admin.promo_max_uses') }}" small="{{ __('admin.promo_max_uses_hint') }}"/></div>
                    <div class="col-md-2"><x-form-input type="date" name="starts_at" title="{{ __('admin.promo_starts') }}"/></div>
                    <div class="col-md-2"><x-form-input type="date" name="ends_at" title="{{ __('admin.promo_ends') }}"/></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary mt-3"><i class="fa fa-plus"></i> {{ __('admin.add') }}</button></div>
                </form>

                <table class="table table-hover mt-4">
                    <thead>
                        <th>{{ __('admin.promo_code') }}</th>
                        <th>{{ __('admin.promo_kind') }}</th>
                        <th>{{ __('admin.promo_value') }}</th>
                        <th>{{ __('admin.promo_used') }}</th>
                        <th>{{ __('admin.promo_period') }}</th>
                        <th>{{ __('admin.status') }}</th>
                        <th>{{ __('admin.actions') }}</th>
                    </thead>
                    <tbody>
                    @forelse($codes as $code)
                        <tr>
                            <td><strong>{{ $code->code }}</strong></td>
                            <td>{{ __('admin.promo_kind_'.$code->kind) }}</td>
                            <td>{{ $code->value }}{{ $code->kind === 'discount' ? '%' : '' }}</td>
                            <td>{{ $code->used_count }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}</td>
                            <td>{{ $code->starts_at?->format('d.m.Y') ?? '—' }} – {{ $code->ends_at?->format('d.m.Y') ?? '—' }}</td>
                            <td>{{ $code->problem() ? __('points.'.$code->problem()) : __('admin.promo_works') }}</td>
                            <td>
                                <form action="{{ route('admin.promo_codes.toggle', $code) }}" method="post" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ $code->active ? __('admin.promo_switch_off') : __('admin.promo_switch_on') }}</button>
                                </form>
                                <form action="{{ route('admin.promo_codes.delete', $code) }}" method="post" class="d-inline" onsubmit="return confirm(I18N.promo_code_delete_confirm)">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $codes->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
