@extends('layouts.app')

@section('content')
<div class="umami-login">
    <div class="umami-login-card">
        <div class="umami-login-head">
            <img src="/brand/logo-horizontal.svg" alt="autopass" style="height:28px;width:auto">
            <div>
                <div class="umami-login-sub">{{ __('admin.partner_panel') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('partner.login.submit') }}">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">{{ __('admin.username') }}</label>
                <input id="username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" required autofocus>
                @error('username')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">{{ __('admin.password') }}</label>
                <input id="password" type="password" class="form-control" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">{{ __('admin.login') }}</button>
        </form>
    </div>
</div>
@endsection
