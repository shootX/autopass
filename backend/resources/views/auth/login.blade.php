@extends('layouts.app')

@section('content')
<div class="umami-login">
    <div class="umami-login-card">
        <div class="umami-login-head">
            <span class="umami-mark" aria-hidden="true">G</span>
            <div>
                <div class="umami-login-title">GeoCar</div>
                <div class="umami-login-sub">{{ __('admin.login') }}</div>
            </div>
            <div class="umami-lang">
                <a class="{{ app()->getLocale() === 'ka' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'ka']) }}">ქარ</a>
                <a class="{{ app()->getLocale() === 'en' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">EN</a>
            </div>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('admin.email') }}</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                @error('email')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ __('admin.password') }}</label>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                @error('password')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">{{ __('admin.remember') }}</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">{{ __('admin.login') }}</button>

            @if (Route::has('password.request'))
                <a class="umami-login-forgot" href="{{ route('password.request') }}">{{ __('admin.forgot') }}</a>
            @endif
        </form>
    </div>
</div>
@endsection
