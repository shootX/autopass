@php
    $tiles = [
        [__('admin.p_kpi_ltv'), number_format($kpi['ltv'], 2).' ₾', __('admin.p_kpi_payments', ['count' => $kpi['payments_count']])],
        [__('admin.p_kpi_visits'), $kpi['visits'], $kpi['avg_interval_days'] !== null ? __('admin.p_kpi_every', ['count' => $kpi['avg_interval_days']]) : '—'],
        [__('admin.p_kpi_last_visit'), $kpi['last_visit'] ? $kpi['last_visit']->format('d.m.Y') : '—', $kpi['days_since_visit'] !== null ? __('admin.p_kpi_days_ago', ['count' => $kpi['days_since_visit']]) : __('admin.p_kpi_never')],
        [__('admin.p_kpi_usage'), $kpi['usage_pct'] !== null ? $kpi['usage_pct'].'%' : '—', $kpi['washes_used'].' / '.$kpi['washes_bought']],
        [__('admin.p_kpi_active_package'), $kpi['active_package'] ? ($kpi['active_package']->number_of_washes - $kpi['active_package']->used_washes).' '.__('admin.p_kpi_left') : '—', $kpi['active_package'] ? __('admin.p_kpi_until', ['date' => \Carbon\Carbon::parse($kpi['active_package']->end_date)->format('d.m.Y')]) : __('admin.p_kpi_packages', ['count' => $kpi['packages_count']])],
        [__('admin.p_kpi_reliability'), $kpi['bookings'], __('admin.p_kpi_cancel', ['cancel' => $kpi['cancel_pct'] ?? 0, 'noshow' => $kpi['no_show_pct'] ?? 0])],
        [__('admin.p_kpi_favourite'), $kpi['favourite_wash']?->name ?? '—', $kpi['favourite_wash'] ? __('admin.p_kpi_of_visits', ['count' => $kpi['favourite_wash_visits'], 'total' => $kpi['visits']]) : ''],
        [__('admin.p_kpi_rating'), $kpi['avg_stars'] !== null ? $kpi['avg_stars'].' ★' : '—', __('admin.p_kpi_reviews', ['count' => $kpi['reviews_count']])],
        [__('admin.p_kpi_points_spent'), $kpi['points_spent'], __('admin.p_kpi_on_vouchers')],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach($tiles as $tile)
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ $tile[0] }}</div>
                    <div class="fs-4 fw-semibold">{{ $tile[1] }}</div>
                    <div class="text-muted small">{{ $tile[2] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
