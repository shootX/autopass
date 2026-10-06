<nav class="sb-topnav navbar navbar-expand">
    <button class="umami-icon-btn" id="sidebarToggle" type="button" aria-label="Menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="umami-top-fade" aria-hidden="true"></div>
    <ul class="navbar-nav ms-auto me-3">
        <li class="nav-item">
            <div class="umami-lang">
                <a class="{{ app()->getLocale() === 'ka' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'ka']) }}">ქარ</a>
                <a class="{{ app()->getLocale() === 'en' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">EN</a>
            </div>
        </li>
    </ul>
</nav>
