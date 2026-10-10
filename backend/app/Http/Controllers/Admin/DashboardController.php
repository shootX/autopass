<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarWash;
use App\Models\CorporateClient;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserTicket;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $weekAgo = now()->subDays(6)->startOfDay();
        $from = now()->subDays(13)->startOfDay();

        $clients = User::query()->where('role', User::ROLE_USER);
        $stats = [
            'clients' => (clone $clients)->count(),
            'clients_week' => (clone $clients)->where('created_at', '>=', $weekAgo)->count(),
            'banned' => (clone $clients)->where('ban', true)->count(),
            'managers' => User::query()->where('role', User::ROLE_MANAGER)->count(),
            'partners' => CorporateClient::query()->count(),
            'washes' => CarWash::query()->count(),
            'cars' => UserCar::query()->count(),
            'brands' => CarBrand::query()->count(),
            'appointments' => Appointment::query()->count(),
            'appointments_today' => Appointment::query()->whereDate('date', $today)->count(),
            'appointments_pending' => Appointment::query()->where('approved', false)->count(),
            'packages_active' => UserPackage::query()->whereDate('end_date', '>=', $today)->count(),
            'packages_total' => UserPackage::query()->count(),
            'reviews' => Review::query()->count(),
            'reviews_avg' => round((float) Review::query()->avg('stars'), 1),
            'vouchers' => Voucher::query()->count(),
            'vouchers_issued' => UserVoucher::query()->count(),
            'tickets' => Ticket::query()->count(),
            'tickets_issued' => UserTicket::query()->count(),
            'revenue' => (float) Transaction::query()->where('status', true)->where('type', 'income')->sum('amount'),
            'payments' => Transaction::query()->where('status', true)->where('type', 'income')->count(),
        ];

        $appointmentDays = Appointment::query()
            ->whereBetween('date', [$from->toDateString(), $today])
            ->selectRaw('date as day, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'day');

        $clientDays = User::query()
            ->where('role', User::ROLE_USER)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        $trend = collect(range(0, 13))->map(function (int $offset) use ($from, $appointmentDays, $clientDays) {
            $day = $from->copy()->addDays($offset);
            $key = $day->toDateString();

            return [
                'label' => $day->format('d.m'),
                'appointments' => (int) ($appointmentDays[$key] ?? $appointmentDays[$day->format('Y-m-d')] ?? 0),
                'clients' => (int) $this->dayCount($clientDays, $day),
            ];
        });

        $maxTrend = max(1, $trend->max('appointments'), $trend->max('clients'));

        $washes = Appointment::query()
            ->join('car_washes', 'car_washes.id', '=', 'appointments.car_wash_id')
            ->selectRaw('car_washes.name as label, COUNT(*) as total')
            ->groupBy('car_washes.id', 'car_washes.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $packages = UserPackage::query()
            ->join('packages', 'packages.id', '=', 'user_packages.package_id')
            ->selectRaw('packages.car_type as label, COUNT(*) as total')
            ->groupBy('packages.car_type')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $recent = Appointment::query()
            ->with(['user', 'washing'])
            ->latest('id')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'trend', 'maxTrend', 'washes', 'packages', 'recent'));
    }

    private function dayCount($counts, Carbon $day): int
    {
        $key = $day->toDateString();
        foreach ($counts as $date => $total) {
            if (Carbon::parse($date)->toDateString() === $key) {
                return (int) $total;
            }
        }

        return 0;
    }
}
