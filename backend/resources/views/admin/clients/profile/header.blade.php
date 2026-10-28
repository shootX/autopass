<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h4 class="mb-1">
                    {{ $client->titleName() }}
                    <span class="badge {{ $segmentClasses[$segment] ?? 'bg-secondary' }}">{{ __('admin.p_seg_'.$segment) }}</span>
                    @if($isVip)<span class="badge bg-dark">VIP</span>@endif
                    @if($client->ban)<span class="badge bg-danger">{{ __('admin.p_banned') }}</span>@endif
                </h4>
                <div class="text-muted">ID {{ $client->id }}</div>
            </div>
            <div class="btn-group">
                <a class="btn btn-sm btn-outline-secondary" href="tel:{{ \App\Support\Phone::digits($client->phone) }}"><i class="fas fa-phone"></i></a>
                <a class="btn btn-sm btn-primary" href="{{ route('admin.clients.edit', $client) }}"><i class="fas fa-edit"></i> {{ __('admin.edit') }}</a>
                <a class="btn btn-sm btn-success" href="{{ route('admin.clients.packages', $client->id) }}"><i class="fas fa-box"></i> {{ __('admin.packages') }}</a>
                <a class="btn btn-sm btn-secondary" href="{{ route('admin.clients.cars', $client->id) }}"><i class="fas fa-car"></i> {{ __('admin.p_tab_cars') }}</a>
                <form method="POST" action="{{ route('admin.clients.ban', $client) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-dark"><i class="fas fa-ban"></i></button></form>
            </div>
        </div>
        <hr>
        <div class="row g-3 small">
            <div class="col-md-3"><div class="text-muted">{{ __('admin.phone') }}</div>{{ \App\Support\Phone::format($client->phone) }}</div>
            <div class="col-md-3"><div class="text-muted">Email</div>{{ $client->email ?: '—' }}</div>
            <div class="col-md-3"><div class="text-muted">{{ __('admin.sex') }}</div>{{ $client->sex === 'male' ? __('admin.male') : ($client->sex === 'female' ? __('admin.female') : '—') }}</div>
            <div class="col-md-3">
                <div class="text-muted">{{ __('admin.birth_date') }}</div>
                @if($client->date_of_birth)
                    {{ \Carbon\Carbon::parse($client->date_of_birth)->format('d.m.Y') }} ({{ __('admin.p_age', ['count' => \Carbon\Carbon::parse($client->date_of_birth)->age]) }})
                @else — @endif
            </div>
            <div class="col-md-3">
                <div class="text-muted">{{ __('admin.registered_at') }}</div>
                {{ $fmt($client->created_at) }}
                @if($client->created_at)<span class="text-muted">({{ __('admin.p_days_with_us', ['count' => (int) \Carbon\Carbon::parse($client->created_at)->diffInDays(now())]) }})</span>@endif
            </div>
            <div class="col-md-3">
                <div class="text-muted">{{ __('admin.p_points') }}</div>
                {{ (int) $client->points }}
                @if((int) $client->test_points > 0)<span class="text-muted">({{ __('admin.p_points_split', ['real' => $client->realPoints(), 'test' => (int) $client->test_points]) }})</span>@endif
                <form action="{{ route('admin.clients.points', $client) }}" method="post" class="d-flex gap-1 mt-1" onsubmit="return confirm(I18N.points_adjust_confirm)">@csrf
                    <input type="number" name="delta" class="form-control form-control-sm" style="max-width:90px" placeholder="±" required>
                    <input type="text" name="comment" class="form-control form-control-sm" maxlength="120" placeholder="{{ __('admin.points_adjust_reason') }}" required>
                    <button class="btn btn-sm btn-outline-primary">{{ __('admin.points_adjust') }}</button>
                </form>
            </div>
            <div class="col-md-3">
                <div class="text-muted">{{ __('admin.p_referral') }}</div>
                @if($referrer)
                    <a href="{{ route('admin.clients.show', $referrer) }}">{{ $referrer->titleName() }}</a>
                @else — @endif
                · {{ __('admin.p_ref_code') }}: {{ $client->ref_code ?: '—' }}
                · {{ __('admin.p_invited', ['count' => $referralsCount]) }}
            </div>
            <div class="col-md-3">
                <div class="text-muted">{{ __('admin.p_push') }}</div>
                {{ $hasPushToken ? __('admin.p_push_device') : __('admin.p_push_no_device') }}
                ·
                @foreach(['enable_push_wash_appointment' => 'p_push_booking', 'enable_push_renewal_subscription' => 'p_push_renewal', 'enable_push_special_promotions' => 'p_push_promo'] as $field => $label)
                    <span class="badge {{ $client->$field ? 'bg-success' : 'bg-secondary' }}">{{ __('admin.'.$label) }}</span>
                @endforeach
            </div>
        </div>
    </div>
</div>
