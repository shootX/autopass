<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;

class ShopController extends Controller
{
    public function home()
    {
        $user = auth()->user();

        // Still sold only, with how many this user owns and may still buy (owned, left).
        $vouchers = Voucher::query()
            ->onSale()
            ->with('category:id,name')
            ->latest('id')
            ->limit(50)
            ->get();
        Voucher::fillForUser($vouchers, (int) $user->id);

        // One row per purchase, so two of the same offer open their own codes: id is the purchase, voucher_id
        // the offer. Expired ones are in the history only.
        $myVouchers = $user->vouchers()
            ->withPivot('id', 'code')
            ->notExpired()
            ->with('category:id,name')
            ->limit(30)
            ->get()
            ->map(fn (Voucher $item) => array_merge($item->toArray(), [
                'id' => (int) $item->pivot->id,
                'voucher_id' => (int) $item->id,
                'code' => $item->pivot->code ?? null,
            ]))
            ->values();

        return response()->json([
            'success' => true,
            'vouchers' => $vouchers,
            'my_vouchers' => $myVouchers,
        ]);
    }
}
