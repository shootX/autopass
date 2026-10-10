<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>autopass</title>
    <link rel="icon" href="/brand/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#14482F">

    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="/css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css" integrity="sha512-0V10q+b1Iumz67sVDL8LPFZEEavo6H/nBSyghr7mm9JEQkOAm91HNoZQRvQdjennBb/oEuW+8oZHVpIKq+d25g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js" integrity="sha512-zP5W8791v1A6FToy+viyoyUUyjCzx+4K8XZCKzW28AnCoepPNIXecxh9mvGuy3Rt78OzEsU+VCvcObwAMvBAww==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>


    <!-- Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Or for RTL support -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css" />

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js"></script>

    <script src="
    https://cdn.jsdelivr.net/npm/lightbox2@2.12.0/dist/js/lightbox.min.js
    "></script>
        <link href="
    https://cdn.jsdelivr.net/npm/lightbox2@2.12.0/dist/css/lightbox.min.css
    " rel="stylesheet">

    @stack('css')
    <link href="/css/umami-admin.css" rel="stylesheet" />
</head>
<body class="sb-nav-fixed">
@include('admin.layout.header')
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        @include('admin.layout.sidebar')
    </div>
    <div id="layoutSidenav_content">
        <main>
            @yield('content')
        </main>
        @include('admin.layout.footer')
    </div>
</div>
@stack('modals')
<script>
    window.I18N = @json(__('admin'));
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="/js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
<script src="/js/datatables-simple-demo.js"></script>
<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    setTimeout(function(){
        $(".alert.alert-success").slideUp();
    }, 3000);

    window.postAction = function (url) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        const token = document.querySelector('meta[name="csrf-token"]');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = token ? token.getAttribute('content') : '';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    };
</script>
@stack('js')
</body>
</html>
