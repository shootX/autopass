<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VouchersController extends Controller
{
    public function getVouchers(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:App\Models\VoucherCategory,id'
        ]);

        if($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'category_id is invalid'
            ]);
        }

        $vouchers = Voucher::with('category');

        if($request->has('category_id'))
        {
            $vouchers->where('category_id', $request->category_id);
        }
        $vouchers = $vouchers->paginate(50);
        return response()->json([
            'success' => true,
            'vouchers' => $vouchers
        ]);
    }

    public function getCategories(Request $request)
    {
        $categories = VoucherCategory::paginate(10);
        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    private function generateVoucher(array $data, int $attempts = 5)
    {
        do {
            try {
                return UserVoucher::create([
                    ...$data,
                    'code' => strtoupper(Str::random(8)),
                ]);
            } catch (QueryException $e) {
                // 1062 — duplicate entry (MySQL)
                if ($e->getCode() != 23000) {
                    throw $e; // другая ошибка — пробрасываем
                }
            }
        } while (--$attempts > 0);

        throw new \Exception('Some error');
    }

    public function buyVoucher(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'voucher_id' => 'required|exists:App\Models\Voucher,id',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'message' => $validate->errors()]);
        }

        $user = auth()->user();

        $voucher = Voucher::find($request->voucher_id);

        if($voucher->price <= $user->points)
        {
            try {
                $this->generateVoucher([
                    'user_id' => $user->id,
                    'voucher_id' => $voucher->id,
                ]);

                DB::transaction(function () use ($user, $voucher) {
                    $user->points -= $voucher->price;
                    $user->update();

                    Transaction::create([
                        'user_id' => $user->id,
                        'amount' => $voucher->price,
                        'type' => 'outcome',
                        'system' => 'Points',
                        'status' => true,
                        'comment' => 'Voucher'
                    ]);
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Voucher purchased successfully',
                    'points' => $user->points
                ]);
            } catch (\Exception $ex)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Some error'
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Not enough points'
            ]);
        }
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
                'vouchers' => $v
            ]);
        } catch (\Exception $ex)
        {
            return response()->json([
                'success' => false,
                'message' => 'Some error'
            ]);
        }
    }
}
