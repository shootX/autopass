<?php

namespace App\Http\Controllers\WebHook;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use DragonCode\Support\Facades\Helpers\Str;
use Illuminate\Http\Request;
use App\Models\UserPackage;
use Illuminate\Support\Facades\DB;
use PHPUnit\Exception;
use Carbon\Carbon;

class FlittWebHookController extends Controller
{
    public function callback(Request $request)
    {
        try{
            $data = $request->getContent();
            $payment = json_decode($data);

            file_put_contents(public_path('flitt.log'), $data, FILE_APPEND);

            if ($payment->order_status == 'approved') {

                $paydata = explode(',', $payment->merchant_data);

                $type = $paydata[0];

                if($type == "package")
                {
                    $userid = $paydata[1];
                    $carid = $paydata[2];
                    $packageid = $paydata[3];
                    $month = (int)$paydata[4];
                    $priceID = $paydata[5] ?? null;

                    $package = Package::find($packageid)->first();

                    $userPackage = [
                        'user_id' => $userid,
                        'user_car_id' => $carid,
                        'package_id' => $packageid,
                        'price_id' => $priceID,
                        'start_date' => Carbon::now(),
                        'end_date' => Carbon::now()->addMonths($month),
                        'number_of_washes' => $package->count_washes,
                        'used_washes' => 0,
                        'qr_code' => Str::random(16)
                    ];

                    if (isset($payment->rectoken) && !empty($payment->rectoken)) {
                        $userPackage['rectoken'] = $payment->rectoken;
                    }

                    UserPackage::create($userPackage);

                    //todo send push
                }

                if($type == "points")
                {
                    $userId = $paydata[1];

                    $amount = (int)$payment->amount / 100;

                    DB::transaction(function () use ($userId, $amount) {

                        $user = User::lockForUpdate()->find($userId);

                        $user->points += $amount;
                        $user->update();

                        Transaction::create([
                            'user_id' => $user->id,
                            'amount' => $amount,
                            'type' => 'income',
                            'system' => 'Flitt',
                            'status' => true,
                            'comment' => 'Points'
                        ]);

                    });
                    //todo send push
                }
            }

        } catch (Exception $e) {
            ;
        }

        return response('OK', 200);
    }
}
