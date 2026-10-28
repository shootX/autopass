<?php

namespace App\Http\Controllers\Api\Partners;

use App\Http\Controllers\Controller;
use App\Models\UserVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class VoucherHistoryController extends Controller
{
    /** GET /api/partner/vouchers/history: the last 100 vouchers this partner redeemed. No client data beyond a masked name. */
    public function index(): JsonResponse
    {
        $partnerId = (int) auth('partners')->id();
        $rows = UserVoucher::query()
            ->whereNotNull('used_at')
            ->where('used_by_partner_id', $partnerId)
            ->with(['voucher:id,name', 'user:id,name,surname'])
            ->latest('used_at')
            ->limit(100)
            ->get()
            ->map(fn (UserVoucher $row) => [
                'code' => $row->code,
                'voucher' => $row->voucher?->name,
                'client' => $row->user ? trim($row->user->name.' '.mb_substr((string) $row->user->surname, 0, 1).'.') : null,
                'used_at' => $row->used_at ? Carbon::parse($row->used_at)->toIso8601String() : null,
            ]);

        return response()->json(['success' => true, 'vouchers' => $rows]);
    }
}
