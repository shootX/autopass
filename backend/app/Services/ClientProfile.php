<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CarWash;
use App\Models\PackageWash;
use App\Models\Review;
use App\Models\TbcPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserVoucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Everything the admin client profile shows: history per section, KPIs and a segment. Read-only. */
class ClientProfile
{
    /** Rows shown per history tab; the KPIs always count everything. */
    public const TAB_LIMIT = 200;

    public const NEW_DAYS = 30;

    public const ACTIVE_DAYS = 30;

    public const LAPSED_DAYS = 60;

    public const EXPIRY_WARN_DAYS = 7;

    public const VIP_SHARE = 0.10;

    public function __construct(private User $client) {}

    public function data(): array
    {
        $client = $this->client;
        $packages = UserPackage::query()->where('user_id', $client->id)
            ->with(['package', 'car.model.brand'])->orderByDesc('start_date')->get();
        $appointments = Appointment::query()->where('user_id', $client->id)
            ->with(['washing', 'car.model.brand', 'services'])->orderByDesc('date')->orderByDesc('time')->get();
        $washes = PackageWash::query()->whereIn('user_package_id', $packages->pluck('id'))
            ->with(['washing', 'manager', 'userPackage.car'])->orderByDesc('created_at')->get();
        $payments = TbcPayment::query()->where('user_id', $client->id)->orderByDesc('created_at')->get();
        $transactions = Transaction::query()->where('user_id', $client->id)->orderByDesc('created_at')->get();
        $cars = UserCar::query()->where('user_id', $client->id)->with('model.brand')->orderByDesc('created_at')->get();
        $vouchers = UserVoucher::query()->where('user_id', $client->id)
            ->with(['voucher', 'partner'])->orderByDesc('created_at')->get();
        $tickets = DB::table('user_tickets')->join('tickets', 'tickets.id', '=', 'user_tickets.ticket_id')
            ->where('user_tickets.user_id', $client->id)
            ->select('tickets.name', 'tickets.price', 'user_tickets.created_at')
            ->orderByDesc('user_tickets.created_at')->get();
        $reviews = Review::query()->where('user_id', $client->id)->with('washing')->orderByDesc('created_at')->get();
        $audit = DB::table('admin_audit_logs')->leftJoin('users as admins', 'admins.id', '=', 'admin_audit_logs.admin_id')
            ->where('target_type', 'user')->where('target_id', $client->id)
            ->select('admin_audit_logs.*', 'admins.name as admin_name', 'admins.surname as admin_surname')
            ->orderByDesc('admin_audit_logs.created_at')->get();

        $visits = $this->visits($washes, $appointments);
        $kpi = $this->kpi($packages, $appointments, $payments, $transactions, $reviews, $visits);

        return [
            'client' => $client,
            'referrer' => $client->referral,
            'referralsCount' => User::query()->where('referral_id', $client->id)->count(),
            'hasPushToken' => $client->pushTokens()->exists(),
            'kpi' => $kpi,
            'segment' => $this->segment($kpi, $packages),
            'isVip' => $this->isVip($kpi['ltv']),
            'timeline' => $this->timeline($cars, $appointments, $washes, $payments, $transactions, $vouchers, $reviews, $audit),
            'packages' => $packages->take(self::TAB_LIMIT),
            'appointments' => $appointments->take(self::TAB_LIMIT),
            'washes' => $washes->take(self::TAB_LIMIT),
            'payments' => $payments->take(self::TAB_LIMIT),
            'transactions' => $transactions->take(self::TAB_LIMIT),
            'cars' => $cars,
            'vouchers' => $vouchers->take(self::TAB_LIMIT),
            'tickets' => $tickets->take(self::TAB_LIMIT),
            'reviews' => $reviews->take(self::TAB_LIMIT),
            'audit' => $audit->take(self::TAB_LIMIT),
        ];
    }

    /**
     * One row per time the client was actually served: each package wash, plus each
     * completed booking that no package wash already stands for.
     */
    private function visits(Collection $washes, Collection $appointments): Collection
    {
        $linked = $washes->pluck('appointment_id')->filter()->flip();
        $fromWashes = $washes->map(fn (PackageWash $w) => [
            'at' => Carbon::parse($w->created_at), 'car_wash_id' => $w->car_wash_id, 'car_id' => $w->userPackage?->user_car_id,
        ]);
        $fromBookings = $appointments
            ->filter(fn (Appointment $a) => $a->approved === AppointmentStatus::Completed->value && ! $linked->has($a->id))
            ->map(fn (Appointment $a) => [
                'at' => self::appointmentAt($a), 'car_wash_id' => $a->car_wash_id, 'car_id' => $a->car_id,
            ]);

        return $fromWashes->concat($fromBookings)->sortBy('at')->values();
    }

    private function kpi(Collection $packages, Collection $appointments, Collection $payments, Collection $transactions, Collection $reviews, Collection $visits): array
    {
        $paid = $payments->filter(fn (TbcPayment $p) => $p->status === 'succeeded' && ! $p->test_mode);
        $first = $visits->first()['at'] ?? null;
        $last = $visits->last()['at'] ?? null;
        $bought = (int) $packages->sum('number_of_washes');
        $active = $packages->first(fn (UserPackage $p) => Carbon::parse($p->end_date)->isFuture() && $p->used_washes < $p->number_of_washes);
        $booked = $appointments->count();
        $favouriteId = $visits->countBy('car_wash_id')->sortDesc()->keys()->first();

        return [
            'ltv' => round((float) $paid->sum('amount'), 2),
            'payments_count' => $paid->count(),
            'packages_count' => $packages->count(),
            'active_package' => $active,
            'visits' => $visits->count(),
            'first_visit' => $first,
            'last_visit' => $last,
            'days_since_visit' => $last ? (int) $last->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null,
            'avg_interval_days' => $visits->count() > 1 ? round($first->diffInDays($last) / ($visits->count() - 1), 1) : null,
            'washes_bought' => $bought,
            'washes_used' => (int) $packages->sum('used_washes'),
            'usage_pct' => $bought > 0 ? (int) round($packages->sum('used_washes') / $bought * 100) : null,
            'bookings' => $booked,
            'cancelled' => $appointments->where('approved', AppointmentStatus::Cancelled->value)->count(),
            'no_show' => $appointments->where('approved', AppointmentStatus::Expired->value)->count(),
            'cancel_pct' => $booked > 0 ? (int) round($appointments->where('approved', AppointmentStatus::Cancelled->value)->count() / $booked * 100) : null,
            'no_show_pct' => $booked > 0 ? (int) round($appointments->where('approved', AppointmentStatus::Expired->value)->count() / $booked * 100) : null,
            'favourite_wash' => $favouriteId ? CarWash::find($favouriteId) : null,
            'favourite_wash_visits' => $favouriteId ? $visits->where('car_wash_id', $favouriteId)->count() : 0,
            'avg_stars' => $reviews->count() > 0 ? round((float) $reviews->avg('stars'), 1) : null,
            'reviews_count' => $reviews->count(),
            'car_visits' => $visits->countBy('car_id'),
            'points_spent' => (int) $transactions->where('type', 'outcome')->where('system', 'Points')->sum('amount'),
        ];
    }

    /** One label for where the client stands; thresholds are the constants above. */
    private function segment(array $kpi, Collection $packages): string
    {
        $client = $this->client;
        if ($client->created_at && Carbon::parse($client->created_at)->gt(now()->subDays(self::NEW_DAYS))) {
            return 'new';
        }
        if ($packages->isEmpty() && $kpi['ltv'] <= 0 && $kpi['visits'] === 0) {
            return 'never_bought';
        }
        $expiring = $packages->first(fn (UserPackage $p) => ! $p->rectoken
            && Carbon::parse($p->end_date)->isFuture()
            && Carbon::parse($p->end_date)->lte(now()->addDays(self::EXPIRY_WARN_DAYS)));
        $days = $kpi['days_since_visit'];
        if ($days !== null && $days > self::LAPSED_DAYS) {
            return 'lapsed';
        }
        if ($expiring || $days === null || $days > self::ACTIVE_DAYS) {
            return 'at_risk';
        }

        return 'active';
    }

    /** In the top VIP_SHARE of paying clients by real money paid. */
    private function isVip(float $ltv): bool
    {
        if ($ltv <= 0) {
            return false;
        }
        $totals = TbcPayment::query()->where('status', 'succeeded')->where('test_mode', false)
            ->groupBy('user_id')->selectRaw('user_id, SUM(amount) as total');
        $paying = DB::query()->fromSub($totals, 't')->count();
        $above = DB::query()->fromSub($totals, 't')->where('total', '>', $ltv)->count();

        return $paying > 0 && $above < max(1, (int) ceil($paying * self::VIP_SHARE));
    }

    private function timeline(Collection ...$sources): Collection
    {
        [$cars, $appointments, $washes, $payments, $transactions, $vouchers, $reviews, $audit] = $sources;
        $events = collect([['at' => Carbon::parse($this->client->created_at), 'type' => 'registered', 'text' => '']]);
        $events = $events
            ->concat($cars->map(fn ($c) => ['at' => Carbon::parse($c->created_at), 'type' => 'car', 'text' => self::carLabel($c)]))
            ->concat($appointments->map(fn ($a) => ['at' => Carbon::parse($a->created_at), 'type' => 'booking',
                'text' => trim(($a->washing?->name ?? '').' · '.self::appointmentAt($a)->format('d.m.Y H:i')), 'status' => $a->approved]))
            ->concat($washes->map(fn ($w) => ['at' => Carbon::parse($w->created_at), 'type' => 'wash', 'text' => (string) $w->washing?->name]))
            ->concat($payments->where('status', 'succeeded')->map(fn ($p) => ['at' => Carbon::parse($p->paid_at ?? $p->created_at),
                'type' => 'payment', 'text' => number_format((float) $p->amount, 2).' ₾ · '.$p->type.($p->test_mode ? ' (test)' : '')]))
            ->concat($transactions->where('system', 'Points')->map(fn ($t) => ['at' => Carbon::parse($t->created_at),
                'type' => 'points', 'text' => ($t->type === 'outcome' ? '−' : '+').(int) $t->amount.' · '.$t->comment]))
            ->concat($vouchers->map(fn ($v) => ['at' => Carbon::parse($v->created_at), 'type' => 'voucher', 'text' => (string) $v->voucher?->name]))
            ->concat($reviews->map(fn ($r) => ['at' => Carbon::parse($r->created_at), 'type' => 'review',
                'text' => str_repeat('★', (int) $r->stars).' · '.($r->washing?->name ?? '')]))
            ->concat($audit->map(fn ($l) => ['at' => Carbon::parse($l->created_at), 'type' => 'admin',
                'text' => $l->action.($l->field ? ' · '.$l->field : '')]));

        return $events->sortByDesc('at')->values()->take(self::TAB_LIMIT);
    }

    public static function appointmentAt(Appointment $a): Carbon
    {
        return Carbon::parse(Carbon::parse($a->date)->toDateString().' '.($a->time ?: '00:00'));
    }

    public static function carLabel(?UserCar $car): string
    {
        if (! $car) {
            return '—';
        }

        return trim(($car->model?->brand?->name ?? '').' '.($car->model?->name ?? '').' ('.$car->plate.')');
    }
}
