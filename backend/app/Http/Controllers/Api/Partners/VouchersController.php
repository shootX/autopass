<?php

namespace App\Http\Controllers\Api\Partners;

use App\Http\Controllers\Controller;
use App\Models\SmsTemp;
use App\Models\UserVoucher;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class VouchersController extends Controller
{
    private const MAX_CODE_ATTEMPTS = 5;

    public function checkVoucher(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'code' => 'required|min:8|max:8'
        ]);

        if($validate->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Incorrect code'
            ]);
        }

        try {

            $voucher = UserVoucher::where('code', $request->code)->whereNull('used_at')->first();

            if (! $voucher) {
                return response()->json(['success' => false, 'message' => 'Incorrect code']);
            }

            if (! $this->ownedByPartner($voucher)) {
                return response()->json(['success' => false, 'message' => 'Incorrect code'], 403);
            }

            $checkSms = SmsTemp::where('type', 'use_voucher')
                ->where('user_id', $voucher->user_id)
                ->where('created_at', '>', now()->subMinutes(10))
                ->count();

            if($checkSms >= 2)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'SMS limit exceeded. Wait 10 minutes.'
                ]);
            }

            $code = rand(100000, 999999);

            $sms = SmsTemp::create([
                'type' => 'use_voucher',
                'user_id' => $voucher->user_id,
                'code' => $code,
                'user_voucher_id' => $voucher->id
            ]);

            //Тут отправляем смс
            Http::get('https://smsoffice.ge/api/v2/send/', [
                'key' => env('SMSOFFICE_API_KEY'),
                'destination' => Phone::forSms($voucher->user->phone),
                'sender' => env('SMSOFFICE_SENDER'),
                'content' => 'Voucher use code: ' . $code,
            ]);

            return response()->json([
                'success' => true,
                'temp_code' => $sms->id
            ]);
        } catch (\Exception $ex)
        {
            return response()->json([
                'success' => false,
                'message' => 'Some error'
            ]);
        }
    }

    public function useVoucher(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'temp_code' => 'required|integer',
            'code' => 'required|digits:6'
        ]);

        if($validate->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Incorrect code'
            ]);
        }

        $tempId = (int) $request->temp_code;
        $lockKey = 'voucher-use:'.$tempId;

        // BE-18: 5 wrong codes burn the SMS code; the partner has to request a new one.
        if (RateLimiter::tooManyAttempts($lockKey, self::MAX_CODE_ATTEMPTS)) {
            SmsTemp::whereKey($tempId)->where('type', 'use_voucher')->delete();
            return response()->json(['success' => false, 'message' => 'Too many attempts. Request a new code.'], 429);
        }

        try {
            $sms = SmsTemp::where('id', $tempId)
                ->where('type', 'use_voucher')
                ->where('created_at', '>', now()->subMinutes(10))
                ->first();

            if (! $sms || ! hash_equals((string) $sms->code, (string) $request->code)) {
                RateLimiter::hit($lockKey, 600);
                return response()->json(['success' => false, 'message' => 'Incorrect SMS code']);
            }

            $voucher = UserVoucher::find($sms->user_voucher_id);

            if (! $this->ownedByPartner($voucher)) {
                return response()->json(['success' => false, 'message' => 'Incorrect SMS code'], 403);
            }

            if ($voucher->used_at !== null) {
                return response()->json(['success' => false, 'message' => 'Voucher already used'], 422);
            }

            // BE-19: keep the record, mark it used.
            $voucher->update(['used_at' => now(), 'used_by_partner_id' => auth('partners')->id()]);
            $sms->delete();
            RateLimiter::clear($lockKey);

            return response()->json(['success' => true, 'message' => 'You successfully used voucher']);
        } catch (\Exception $ex) {
            report($ex);
            return response()->json(['success' => false, 'message' => 'Some error']);
        }
    }

    /** Only the partner the voucher belongs to may check or redeem it. */
    private function ownedByPartner(?UserVoucher $userVoucher): bool
    {
        $partnerId = auth('partners')->id();
        $ownerId = $userVoucher?->voucher?->partner_id;

        return $partnerId !== null && $ownerId !== null && (int) $ownerId === (int) $partnerId;
    }
}
