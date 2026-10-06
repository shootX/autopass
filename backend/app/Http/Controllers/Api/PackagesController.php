<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Flitt\Checkout;
use Flitt\Configuration;
use Illuminate\Support\Str;
use PHPUnit\Exception;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PackagesController extends Controller
{
    public function packages(Request $request)
    {
        try {

            $user = auth()->user();

            $validate = Validator::make($request->all(), [
                'carid' => 'required|exists:user_cars,id',
            ]);

            if ($validate->fails()) {
                return response()->json(['success' => false, 'errors' => $validate->errors()]);
            }

            $userCar = $user->cars()->where('id', $request->carid)->first();
            if(!$userCar) {
                return response()->json(['success' => false, 'error' => 'Машина не найдена']);
            }

            $carType = $userCar->model->type;

            $packages = Package::where('car_type', $carType)->get();
            if($packages->isEmpty()) {
                return response()->json(['success' => false, 'error' => 'Пакеты не найдены']);
            }

            $packages = $packages->map(function ($package) {
                return [
                    'id' => $package->id,
                    'car_type' => $package->car_type,
                    'washes' => $package->count_washes,
                    'prices' => $package->prices
                ];
            });

            return response()->json(['success' => true, 'packages' => $packages]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function buyPackage(Request $request)
    {
        try {
            $user = auth()->user();

            $validate = Validator::make($request->all(), [
                'car_id' => 'required|exists:user_cars,id',
                'number_of_washes' => 'required|numeric',
                'renewal' => 'required|boolean',
                'sub_term' => 'required|numeric',
            ]);

            if ($validate->fails()) {
                return response()->json(['success' => false, 'errors' => $validate->errors()]);
            }

            $userCar = $user->cars()->where('id', $request->car_id)->first();
            if(!$userCar) {
                return response()->json(['success' => false, 'error' => 'Машина не найдена']);
            }

            $carType = $userCar->model->type;

            $package = Package::where('car_type', $carType)
                ->where('count_washes', $request->number_of_washes)
                ->first();

            if(!$package) {
                return response()->json(['success' => false, 'error' => 'Пакеты не найдены']);
            }

            $price = $package
                ->prices()
                ->where('month', $request->sub_term)
                ->first();

            if(!$price) {
                return response()->json(['success' => false, 'error' => 'Цена не найдена']);
            }

            $checkMyPackage = $user->packages()
                ->where('package_id', $package->id)
                ->where('user_car_id', $userCar->id)
                ->first();

            if($checkMyPackage) {
                return response()->json(['success' => false, 'error' => 'У вас уже есть такой пакет']);
            }

            $merchantId = env('FLITT_PAY_NUMBER');
            $secretKey = env('FLITT_PAYMENT_KEY');

            if (! filled($merchantId) || ! filled($secretKey)) {
                if (! app()->environment('local')) {
                    return response()->json(['success' => false, 'error' => 'Payment provider is not configured'], 500);
                }

                UserPackage::create([
                    'user_id' => $user->id,
                    'user_car_id' => $userCar->id,
                    'package_id' => $package->id,
                    'price_id' => $price->id,
                    'start_date' => now(),
                    'end_date' => now()->addMonths((int) $price->month),
                    'number_of_washes' => $package->count_washes,
                    'used_washes' => 0,
                    'qr_code' => Str::random(16),
                ]);

                $origin = rtrim((string) $request->headers->get('Origin', 'http://localhost:5173'), '/');

                return response()->json([
                    'success' => true,
                    'url' => $origin.'/my-packages',
                ]);
            }

            Configuration::setMerchantId($merchantId);
            Configuration::setSecretKey($secretKey);

            $merchantData = [
                'type' => 'package',
                'user_id' => auth()->user()->id,
                'car_id' => $userCar->id,
                'package_id' => $package->id,
                'month' => $price->month,
                'price_id' => $price->id
            ];

            $needToken = 'N';
            if($request->renewal) {
                $needToken = 'Y';
            }

            $merchantData = implode(',', $merchantData);
            $checkoutData = [
                'order_id' => time(),
                'order_desc' => 'Оплата пакета услуг',
                'response_url' => route('payment.return'),
                'server_callback_url' => route('callback.flitt.payment'),
                //'server_callback_url' => 'https://b240d3b64f52.ngrok-free.app/callback/flitt/payment',
                'currency' => 'GEL',
                'sender_email' => auth()->user()->email ?: 'test@geocar.ge',
                'merchant_data' => $merchantData,
                'amount' => $price->price * 100,
                'required_rectoken' => $needToken,
            ];

            $data = Checkout::url($checkoutData);
            $url = $data->getUrl();

            return response()->json(['success' => true, 'url' => $url]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function myPackages(Request $request)
    {
        try {
            $user = auth()->user();
            $packages = $user->packages()->with(['package', 'car'])->get();

//            if($packages->isEmpty()) {
//                return response()->json(['success' => false, 'error' => 'Пакеты не найдены']);
//            }

            $packages = $packages->map(function ($package) {
                return [
                    'id' => $package->id,
                    'package' => $package->package,
                    'car' => $package->car,
                    'start_date' => $package->start_date,
                    'end_date' => $package->end_date,
                    'number_of_washes' => $package->number_of_washes,
                    'used_washes' => $package->used_washes,
                    'renewal' => $package->rectoken ? true : false
                ];
            });

            return response()->json(['success' => true, 'packages' => $packages]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function qrCode(Request $request, $id)
    {
        try {

            $user = Auth::user();

            $package = $user->packages()->where('id', $id)->first();

            $package->update([
                'qr_code' => Str::random(16)
            ]);

            $qrCodeSvg = QrCode::format('svg')->size(300)->generate($package->qr_code);

            return response($qrCodeSvg, 200)
                ->header('Content-Type', 'image/svg+xml');
        } catch (\Exception $e)
        {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function removePackage(Request $request, UserPackage $userPackage)
    {
        try{
            $userPackage->delete();
            return response()->json(['success' => true, 'msg' => 'Пакет успешно удалён.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function managerApprove(Request $request, $id)
    {

        $user = Auth::user();

        if($user->role == User::ROLE_MANAGER) {
            $userPackage = UserPackage::where('id', $id)->first();
            $userPackage->used_washes += 1;
            $userPackage->update();

            $userPackage->user->sendPush([
                'en' => 'The wash has begun',
                'ru' => 'Мойка началась',
                'ka' => 'სარეცხი დაიწყო',
            ], [
                'en' => 'The washing process has begun',
                'ru' => 'Процесс мойки начат',
                'ka' => 'სარეცხი პროცესი დაიწყო'
            ]);

            return response()->json(['success' => true, 'package' => $userPackage]);
        }
    }
}
