<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use Illuminate\Http\Request;
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

        $vouchers = Voucher::with('category');

        if ($request->has('category_id')) {
            $vouchers->where('category_id', $request->category_id);
        }
        $vouchers = $vouchers->paginate(50);

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

    private function uniqueCode(): string
    {
        // Picked before the insert: a failed INSERT would abort the surrounding Postgres transaction.
        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(Str::random(8));
            if (! UserVoucher::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a voucher code');
    }

    public function buyVoucher(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'voucher_id' => 'required|exists:App\Models\Voucher,id',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'message' => $validate->errors()]);
        }

        $voucher = Voucher::query()->findOrFail($request->voucher_id);
        $price = (int) $voucher->price;
        $userId = (int) auth()->id();

        try {
            // Balance check, deduction, voucher and ledger row are one unit: any failure rolls all of it back.
            $points = DB::transaction(function () use ($userId, $voucher, $price) {
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                if ($user->realPoints() < $price) {
                    return null;
                }

                $user->points = (int) $user->points - $price;
                $user->save();

                UserVoucher::query()->create([
                    'user_id' => $user->id,
                    'voucher_id' => $voucher->id,
                    'code' => $this->uniqueCode(),
                ]);

                Transaction::query()->create([
                    'user_id' => $user->id,
                    'amount' => $price,
                    'type' => 'outcome',
                    'system' => 'Points',
                    'status' => true,
                    'comment' => 'Voucher',
                ]);

                return (int) $user->points;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Some error']);
        }

        if ($points === null) {
            return response()->json(['success' => false, 'message' => 'Not enough points']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Voucher purchased successfully',
            'points' => $points,
        ]);
    }

    public function myVouchers(Request $request)
    {
        try {
            $user = auth()->user();
            $v = $user->vouchers()->withPivot('code')->paginate(10);
            foreach ($v as $item) {
                $item['code'] = $item->pivot->code;
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
