<ul class="nav nav-tabs mt-4">
    <li class="nav-item"><a class="nav-link @if(Route::is('admin.site.page') && ($page ?? 'home') === 'home') active @endif" href="{{ route('admin.site.page', 'home') }}">{{ __('admin.site_home') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(Route::is('admin.site.page') && ($page ?? '') === 'business') active @endif" href="{{ route('admin.site.page', 'business') }}">{{ __('admin.site_business') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(Route::is('admin.site.settings')) active @endif" href="{{ route('admin.site.settings') }}">{{ __('admin.site_settings') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(Route::is('admin.site.leads')) active @endif" href="{{ route('admin.site.leads') }}">{{ __('admin.site_leads') }}</a></li>
</ul>

@if(session()->has('message'))
    <div class="alert alert-success mt-3">{{ session()->get('message') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
