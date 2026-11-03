<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VouchersController extends Controller
{
    public function getVouchers(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:App\Models\VoucherCategory,id',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'category_id is invalid',
            ]);
        }

        // Only what is still sold: not disabled, not past its last day.
        $vouchers = Voucher::with('category')->onSale();

        if ($request->has('category_id')) {
            $vouchers->where('category_id', $request->category_id);
        }
        $vouchers = $vouchers->paginate(50);
        Voucher::fillForUser($vouchers->getCollection(), (int) auth()->id());

        return response()->json([
            'success' => true,
            'vouchers' => $vouchers,
        ]);
    }

    public function getCategories(Request $request)
    {
        $categories = VoucherCategory::paginate(10);

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /** @return list<string> */
    private function uniqueCodes(int $count): array
    {
        // Picked before the insert: a failed INSERT would abort the surrounding Postgres transaction.
        $codes = [];
        for ($i = 0; $i < 10 * $count && count($codes) < $count; $i++) {
            $code = strtoupper(Str::random(8));
            if (! in_array($code, $codes, true) && ! UserVoucher::query()->where('code', $code)->exists()) {
                $codes[] = $code;
            }
        }
        if (count($codes) < $count) {
            throw new \RuntimeException('Could not generate a voucher code');
        }

        return $codes;
    }

    public function buyVoucher(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'voucher_id' => 'required|exists:App\Models\Voucher,id',
            'qty' => 'sometimes|integer|min:1|max:20',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'message' => $validate->errors()]);
        }

        $qty = (int) $request->input('qty', 1);
        $userId = (int) auth()->id();

        try {
            // Sale rules, balance, deduction, vouchers and ledger row are one unit under row locks: the voucher's
            // (its stock) and the user's (balance and how many they own).
            $result = DB::transaction(function () use ($request, $qty, $userId) {
                $voucher = Voucher::query()->whereKey($request->voucher_id)->lockForUpdate()->firstOrFail();
                // A price below zero would add points: such an offer is not sold, whatever was typed in the admin.
                if ($voucher->deleted || (int) $voucher->price < 0) {
                    return 'Voucher unavailable';
                }
                if ($voucher->expired()) {
                    return 'Voucher expired';
                }
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $owned = UserVoucher::query()->where('voucher_id', $voucher->id)->where('user_id', $user->id)->count();
                $sold = UserVoucher::query()->where('voucher_id', $voucher->id)->count();
                if ($qty > $voucher->leftFor($owned, $sold)) {
                    return 'Voucher limit reached';
                }
                $total = (int) $voucher->price * $qty;
                if ($user->realPoints() < $total) {
                    return 'Not enough points';
                }

                $user->points = (int) $user->points - $total;
                $user->save();

                foreach ($this->uniqueCodes($qty) as $code) {
                    UserVoucher::query()->create(['user_id' => $user->id, 'voucher_id' => $voucher->id, 'code' => $code]);
                }

                Transaction::query()->create([
                    'user_id' => $user->id,
                    'amount' => $total,
                    'type' => 'outcome',
                    'system' => 'Points',
                    'status' => true,
                    'comment' => $qty > 1 ? $qty.' vouchers' : 'Voucher',
                ]);

                return (int) $user->points;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Some error']);
        }

        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Voucher purchased successfully',
            'points' => $result,
        ]);
    }

    /**
     * Every voucher the user bought, newest first, with its status: active (can be shown to the partner), used,
     * expired (past its last day, can't be used) or unavailable (the offer is gone). An offer the admin only
     * disabled stays active for the ones bought before (owner's rule, 2026-11-03).
     */
    public function history(Request $request)
    {
        $rows = UserVoucher::query()
            ->where('user_id', auth()->id())
            ->with(['voucher.category:id,name', 'voucher.partner:id,name', 'partner:id,name'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(function (UserVoucher $row) {
                $voucher = $row->voucher;
                $status = match (true) {
                    $row->used_at !== null => 'used',
                    ! $voucher => 'unavailable',
                    $voucher->expired() => 'expired',
                    default => 'active',
                };

                return [
                    'id' => $row->id,
                    'voucher_id' => $row->voucher_id,
                    'name' => $voucher?->name,
                    'photo' => $voucher?->photo,
                    'percent' => $voucher?->percent,
                    'price' => $voucher?->price,
                    'category' => $voucher?->category?->name,
                    'partner' => $row->partner?->name ?? $voucher?->partner?->name,
                    'code' => $row->code,
                    'status' => $status,
                    'date_to' => $voucher?->date_to ? substr((string) $voucher->date_to, 0, 10) : null,
                    'purchased_at' => $row->created_at?->toIso8601String(),
                    'used_at' => $row->used_at ? Carbon::parse($row->used_at)->toIso8601String() : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'vouchers' => $rows,
        ]);
    }

    public function myVouchers(Request $request)
    {
        try {
            $user = auth()->user();
            // Ones that can still be used: an expired one is in the history only.
            $v = $user->vouchers()->withPivot('id', 'code')->notExpired()->paginate(10);
            foreach ($v as $item) {
                $item['code'] = $item->pivot->code;
                $item['user_voucher_id'] = $item->pivot->id;
                $item['voucher_id'] = $item->id;
            }

            return response()->json([
                'success' => true,
                'vouchers' => $v,
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => 'Some error',
            ]);
        }
    }
}
