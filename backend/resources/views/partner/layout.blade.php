<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>autopass</title>
    <link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#14482F">
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <link href="/css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css" integrity="sha512-0V10q+b1Iumz67sVDL8LPFZEEavo6H/nBSyghr7mm9JEQkOAm91HNoZQRvQdjennBb/oEuW+8oZHVpIKq+d25g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js" integrity="sha512-zP5W8791v1A6FToy+viyoyUUyjCzx+4K8XZCKzW28AnCoepPNIXecxh9mvGuy3Rt78OzEsU+VCvcObwAMvBAww==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" />
    <link href="/css/umami-admin.css" rel="stylesheet" />
</head>
<body class="sb-nav-fixed">
@include('admin.layout.header')
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav accordion" id="sidenavAccordion">
            <div class="umami-side-brand">
                <a href="{{ route('partner.home') }}">
                    <img src="/brand/logo-horizontal-dark.svg" alt="autopass" style="height:28px;width:auto">
                </a>
            </div>
            <div class="sb-sidenav-menu">
                <div class="nav">
                    <div class="sb-sidenav-menu-heading">{{ __('admin.menu') }}</div>
                    <a class="nav-link @if(Route::is('partner.home')) active @endif" href="{{ route('partner.home') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-chart-column"></i></div>
                        {{ __('admin.data_board') }}
                    </a>
                    <a class="nav-link @if(Route::is('partner.fleet')) active @endif" href="{{ route('partner.fleet') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-car"></i></div>
                        {{ __('admin.fleet') }}
                    </a>
                    <a class="nav-link @if(Route::is('partner.reports')) active @endif" href="{{ route('partner.reports') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-file-excel"></i></div>
                        {{ __('admin.reports') }}
                    </a>
                    <a class="nav-link @if(Route::is('partner.account')) active @endif" href="{{ route('partner.account') }}">
                        <div class="sb-nav-link-icon"><i class="fas fa-user"></i></div>
                        {{ __('admin.partner_account') }}
                    </a>
                </div>
            </div>
            <div class="sb-sidenav-footer">
                <div class="umami-user">
                    <span class="umami-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($corporateClient->name, 0, 1)) }}</span>
                    <div class="umami-user-meta">
                        <div class="umami-user-name">{{ $corporateClient->name }}</div>
                        <form action="{{ route('partner.logout') }}" method="post">
                            @csrf
                            <button type="submit">{{ __('admin.logout') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    </div>
    <div id="layoutSidenav_content">
        <main>
            @yield('content')
        </main>
        @include('admin.layout.footer')
    </div>
</div>
<script>
    window.I18N = @json(__('admin'));
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="/js/scripts.js"></script>
<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    setTimeout(function () {
        $(".alert.alert-success").slideUp();
    }, 3000);
</script>
<style>
    .umami-user-meta form { margin: 0; }
    .umami-user-meta button {
        background: none;
        border: 0;
        padding: 0;
        font-size: 12px;
        color: var(--text-muted);
    }
    .umami-user-meta button:hover { color: var(--text-primary); }
</style>
@stack('js')
</body>
</html>
