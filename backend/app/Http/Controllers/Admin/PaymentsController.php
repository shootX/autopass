<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TbcPayment;
use App\Models\UserVoucher;
use Illuminate\Http\Request;

/** Read-only lists across all clients: card payments and partner voucher redemptions. */
class PaymentsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|string|max:32',
            'type' => 'nullable|in:package,points',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'test' => 'nullable|boolean',
        ]);
        $query = TbcPayment::query()->with('user:id,name,surname,phone')->latest('id')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->where('test_mode', (bool) ($filters['test'] ?? false));

        $total = (clone $query)->where('status', 'succeeded')->sum('amount');
        $statuses = TbcPayment::query()->distinct()->orderBy('status')->pluck('status');

        return view('admin.payments.index', [
            'payments' => $query->paginate(30)->withQueryString(),
            'total' => (float) $total,
            'statuses' => $statuses,
            'filters' => $filters,
        ]);
    }

    public function vouchers(Request $request)
    {
        $used = $request->input('state') !== 'active';
        $rows = UserVoucher::query()->with(['user:id,name,surname,phone', 'voucher:id,name,partner_id', 'partner:id,name'])
            ->when($used, fn ($q) => $q->whereNotNull('used_at')->latest('used_at'))
            ->when(! $used, fn ($q) => $q->whereNull('used_at')->latest('id'))
            ->paginate(30)->withQueryString();

        return view('admin.payments.vouchers', ['rows' => $rows, 'used' => $used]);
    }
}
