<?php

namespace App\Http\Controllers\Api\Partners;

use App\Http\Controllers\Controller;
use App\Models\SmsTemp;
use App\Models\UserVoucher;
use App\Services\Security\AuthChallengeException;
use App\Services\Security\SmsChallengeService;
use App\Services\Sms\SmsStatus;
use App\Services\Sms\SmsUserMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VouchersController extends Controller
{
    public function checkVoucher(Request $request, SmsChallengeService $sms)
    {
        $validate = Validator::make($request->all(), [
            'code' => 'required|min:8|max:8|exists:user_vouchers,code',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect code',
            ]);
        }

        $voucher = UserVoucher::query()->where('code', $request->code)->first();
        if (! $voucher || ! $voucher->user) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect code',
            ]);
        }

        $sent = SmsTemp::query()
            ->where('type', 'use_voucher')
            ->where('user_voucher_id', $voucher->id)
            ->where('created_at', '>', now()->subMinutes(10))
            ->count();

        if ($sent >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'SMS limit exceeded. Wait 10 minutes.',
            ]);
        }

        try {
            $issued = $sms->issue($voucher->user, 'use_voucher', [], $voucher->id, false);
        } catch (\Throwable $e) {
            Log::warning('voucher_check_failed', ['exception' => $e::class]);

            return response()->json([
                'success' => false,
                'message' => 'Some error',
            ], 500);
        }

        if (! $issued->result->ok()) {
            return response()->json([
                'success' => false,
                'delivery' => $issued->result->status->value,
                'temp_code' => $issued->result->status === SmsStatus::Unknown ? $issued->publicId : null,
                'message' => SmsUserMessage::forResult($issued->result),
            ], $issued->result->status === SmsStatus::Unknown ? 503 : 422);
        }

        return response()->json([
            'success' => true,
            'temp_code' => $issued->publicId,
            'delivery' => $issued->result->status->value,
        ]);
    }

    public function useVoucher(Request $request, SmsChallengeService $sms)
    {
        $validate = Validator::make($request->all(), [
            'temp_code' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect code',
            ]);
        }

        try {
            $row = $sms->verify((string) $request->temp_code, (string) $request->code, 'use_voucher');
        } catch (AuthChallengeException) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect SMS code',
            ], 422);
        }

        $deleted = DB::transaction(function () use ($row) {
            $voucher = UserVoucher::query()->lockForUpdate()->find($row->user_voucher_id);
            if (! $voucher) {
                return false;
            }
            $voucher->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect SMS code',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'You successfully used voucher',
        ]);
    }
}
