<?php

namespace App\Http\Controllers\Api\Partners;

use App\Http\Controllers\Controller;
use App\Models\SmsTemp;
use App\Models\UserVoucher;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class VouchersController extends Controller
{
    public function checkVoucher(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'code' => 'required|min:8|max:8|exists:user_vouchers,code'
        ]);

        if($validate->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Incorrect code'
            ]);
        }

        try {

            $voucher = UserVoucher::where('code', $request->code)->first();

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
            'temp_code' => 'required|exists:sms_temps,id',
            'code' => 'required|digits:6'
        ]);

        if($validate->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Incorrect code'
            ]);
        }

        try{
        $sms = SmsTemp::where('id', (int)$request->temp_code)
            ->where('type', 'use_voucher')
            ->where('code', $request->code)
            ->first();

        if($sms)
        {
            $voucher = UserVoucher::find($sms->user_voucher_id);

            $voucher->delete();
            $sms->delete();

            return response()->json([
                'success' => true,
                'message' => 'You successfully used voucher'
            ]);

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect SMS code'
            ]);
        }

        } catch (\Exception $ex)
        {
            return response()->json([
                'success' => false,
                'message' => 'Some error'
            ]);
        }
    }
}
