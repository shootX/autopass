@extends('admin.layout.app')

@php
    $statusLabels = [0 => __('admin.pending'), 1 => __('admin.confirmed'), 2 => __('admin.cancelled'), 3 => __('admin.completed'), 4 => __('admin.expired'), 5 => __('admin.rescheduled')];
    $statusClasses = [0 => 'bg-warning text-dark', 1 => 'bg-primary', 2 => 'bg-secondary', 3 => 'bg-success', 4 => 'bg-danger', 5 => 'bg-info text-dark'];
    $segmentClasses = ['new' => 'bg-info text-dark', 'active' => 'bg-success', 'at_risk' => 'bg-warning text-dark', 'lapsed' => 'bg-danger', 'never_bought' => 'bg-secondary'];
    $fmt = fn ($at) => $at ? \Carbon\Carbon::parse($at)->format('d.m.Y H:i') : '—';
    $tabs = [
        'timeline' => [__('admin.p_tab_timeline'), $timeline->count()],
        'bookings' => [__('admin.appointments'), $appointments->count()],
        'washes' => [__('admin.p_tab_washes'), $washes->count()],
        'packages' => [__('admin.packages'), $packages->count()],
        'payments' => [__('admin.p_tab_payments'), $payments->count() + $transactions->count()],
        'cars' => [__('admin.p_tab_cars'), $cars->count()],
        'vouchers' => [__('admin.vouchers'), $vouchers->count() + $tickets->count()],
        'reviews' => [__('admin.reviews'), $reviews->count()],
        'audit' => [__('admin.p_tab_audit'), $audit->count()],
    ];
@endphp

@section('content')
    <div class="container-fluid px-4">

        <div class="mt-4 mb-2">
            <a href="{{ route('admin.clients') }}"><i class="fas fa-arrow-left"></i> {{ __('admin.clients') }}</a>
        </div>

        @include('admin.clients.profile.header')
        @include('admin.clients.profile.kpi')

        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    @foreach($tabs as $key => $tab)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button" role="tab">
                                {{ $tab[0] }} <span class="badge bg-light text-dark">{{ $tab[1] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body tab-content">
                @foreach(array_keys($tabs) as $key)
                    <div class="tab-pane fade @if($loop->first) show active @endif table-responsive" id="tab-{{ $key }}" role="tabpanel">
                        @include('admin.clients.profile.'.$key)
                    </div>
                @endforeach
                <p class="text-muted small mb-0">{{ __('admin.p_tab_limit', ['count' => \App\Services\ClientProfile::TAB_LIMIT]) }}</p>
            </div>
        </div>

    </div>
@endsection
