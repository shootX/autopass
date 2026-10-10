<nav class="sb-sidenav accordion" id="sidenavAccordion">
    <div class="umami-side-brand">
        <a href="{{ route('admin.dashboard') }}">
            <img src="/brand/logo-horizontal-dark.svg" alt="autopass" style="height:28px;width:auto">
        </a>
    </div>
    <div class="sb-sidenav-menu">
        <div class="nav">
            <div class="sb-sidenav-menu-heading">{{ __('admin.menu') }}</div>

            <a class="nav-link @if(Route::is('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-chart-column"></i></div>
                {{ __('admin.dashboard') }}
            </a>
            <a class="nav-link @if(Route::is('admin.clients')) active @endif" href="{{route('admin.clients')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                {{ __('admin.clients') }}
            </a>
            <a class="nav-link @if(Route::is('admin.managers')) active @endif" href="{{route('admin.managers')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-user"></i></div>
                {{ __('admin.managers') }}
            </a>
            <a class="nav-link @if(Route::is('admin.corporate*')) active @endif" href="{{ route('admin.corporate') }}">
                <div class="sb-nav-link-icon"><i class="fa-regular fa-handshake"></i></div>
                {{ __('admin.partners') }}
            </a>
            <a class="nav-link @if(Route::is('admin.appointments')) active @endif" href="{{route('admin.appointments')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-book"></i></div>
                {{ __('admin.appointments') }}
            </a>
            <a class="nav-link @if(Route::is('admin.reports.washes')) active @endif" href="{{ route('admin.reports.washes') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-file-excel"></i></div>
                {{ __('admin.reports') }}
            </a>
            <a class="nav-link @if(Route::is('admin.washings')) active @endif" href="{{route('admin.washings')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-warehouse"></i></div>
                {{ __('admin.washings') }}
            </a>
            <a class="nav-link @if(Route::is('admin.packages.index')) active @endif" href="{{route('admin.packages.index')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-box"></i></div>
                {{ __('admin.packages') }}
            </a>
            <a class="nav-link @if(Route::is('admin.car_brands')) active @endif" href="{{route('admin.car_brands')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-car"></i></div>
                {{ __('admin.brands_models') }}
            </a>
            <a class="nav-link @if(Route::is('admin.body_types') || Route::is('admin.body_types.*')) active @endif" href="{{ route('admin.body_types') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-car-side"></i></div>
                {{ __('admin.body_types') }}
            </a>
            <a class="nav-link @if(Route::is('admin.tickets')) active @endif" href="{{route('admin.tickets')}}">
                <div class="sb-nav-link-icon"><i class="fa-solid fa-ticket-simple"></i></div>
                {{ __('admin.tickets') }}
            </a>
            <a class="nav-link @if(Route::is('admin.vouchers')) active @endif" href="{{route('admin.vouchers')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-sticky-note"></i></div>
                {{ __('admin.vouchers') }}
            </a>
            <a class="nav-link @if(Route::is('admin.vouchers.categories')) active @endif" href="{{route('admin.vouchers.categories')}}">
                <div class="sb-nav-link-icon"><i class="fas fa-sticky-note"></i></div>
                {{ __('admin.voucher_categories') }}
            </a>

            <a class="nav-link @if(Route::is('admin.promo')) active @endif" href="{{route('admin.promo')}}">
                <div class="sb-nav-link-icon"><i class="fa-solid fa-percent"></i></div>
                {{ __('admin.promo_banner') }}
            </a>



        </div>
    </div>
    <div class="sb-sidenav-footer">
        @if(auth()->user())
        <div class="umami-user">
            <span class="umami-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="umami-user-meta">
                <div class="umami-user-name">{{ auth()->user()->name }} {{ auth()->user()->surname }}</div>
                <a href="{{ route('admin.logout') }}">{{ __('admin.logout') }}</a>
            </div>
        </div>
        @endif
    </div>
</nav>
