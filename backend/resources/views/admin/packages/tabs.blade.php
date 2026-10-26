{{-- Packages menu: app packages for every driver, corporate packages made for one company each. --}}
<ul class="nav nav-tabs mt-4">
    <li class="nav-item"><a class="nav-link @if(Route::is('admin.packages.index')) active @endif" href="{{ route('admin.packages.index') }}">{{ __('admin.cp_tab_app') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(Route::is('admin.packages.corporate*')) active @endif" href="{{ route('admin.packages.corporate') }}">{{ __('admin.cp_tab_corporate') }}</a></li>
</ul>
