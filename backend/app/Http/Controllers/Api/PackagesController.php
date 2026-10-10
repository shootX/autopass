<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\TbcPayment;
use App\Services\Payments\FlittCheckout;
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
                return response()->json(['success' => false, 'error' => 'ავტომობილი ვერ მოიძებნა']);
            }

            $carType = $userCar->model->type;

            $packages = Package::where('car_type', $carType)->get();
            if($packages->isEmpty()) {
                return response()->json(['success' => false, 'error' => 'პაკეტი ვერ მოიძებნა']);
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
            \Illuminate\Support\Facades\Log::warning('package_request_failed', ['exception' => $e::class]);
            return response()->json(['success' => false, 'error' => 'Request failed']);
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
                return response()->json(['success' => false, 'error' => 'ავტომობილი ვერ მოიძებნა']);
            }

            $carType = $userCar->model->type;

            $package = Package::where('car_type', $carType)
                ->where('count_washes', $request->number_of_washes)
                ->first();

            if(!$package) {
                return response()->json(['success' => false, 'error' => 'პაკეტი ვერ მოიძებნა']);
            }

            $price = $package
                ->prices()
                ->where('month', $request->sub_term)
                ->first();

            if(!$price) {
                return response()->json(['success' => false, 'error' => 'ფასი ვერ მოიძებნა']);
            }

            $checkMyPackage = $user->packages()
                ->where('package_id', $package->id)
                ->where('user_car_id', $userCar->id)
                ->first();

            if($checkMyPackage) {
                return response()->json(['success' => false, 'error' => 'ამ ავტომობილზე ასეთი პაკეტი უკვე გაქვთ']);
            }

            $payment = TbcPayment::create([
                'merchant_payment_id' => 'P'.strtoupper(Str::random(16)),
                'user_id' => $user->id,
                'type' => 'package',
                'amount' => round((float) $price->price, 2),
                'currency' => 'GEL',
                'status' => 'pending',
                'payload' => [
                    'car_id' => $userCar->id,
                    'package_id' => $package->id,
                    'price_id' => $price->id,
                    'month' => (int) $price->month,
                ],
            ]);

            $url = app(FlittCheckout::class)->checkoutUrl($payment, 'Autopass package');

            return response()->json(['success' => true, 'url' => $url]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('package_request_failed', ['exception' => $e::class]);
            return response()->json(['success' => false, 'error' => 'Request failed']);
        }
    }

    public function myPackages(Request $request)
    {
        try {
            $user = auth()->user();
            $packages = $user->packages()->with(['package', 'car'])->get();

//            if($packages->isEmpty()) {
//                return response()->json(['success' => false, 'error' => 'პაკეტი ვერ მოიძებნა']);
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
            \Illuminate\Support\Facades\Log::warning('package_request_failed', ['exception' => $e::class]);
            return response()->json(['success' => false, 'error' => 'Request failed']);
        }
    }

    public function qrCode(Request $request, $id)
    {
        try {

            $user = Auth::user();

            $package = $user->packages()->where('id', $id)->first();
            if (! $package) {
                return response()->json(['success' => false, 'error' => 'Not found'], 404);
            }

            $package->update([
                'qr_code' => Str::random(16)
            ]);

            $qrCodeSvg = QrCode::format('svg')->size(300)->generate($package->qr_code);

            return response($qrCodeSvg, 200)
                ->header('Content-Type', 'image/svg+xml');
        } catch (\Exception $e)
        {
            \Illuminate\Support\Facades\Log::warning('package_request_failed', ['exception' => $e::class]);
            return response()->json(['success' => false, 'error' => 'Request failed']);
        }
    }

    public function removePackage(Request $request, UserPackage $userPackage)
    {
        if ((int) $userPackage->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $userPackage->delete();

        return response()->json(['success' => true, 'msg' => 'Пакет успешно удалён.']);
    }

    public function managerApprove(Request $request, $id)
    {
        $user = Auth::user();
        $branch = $user?->washing;

        if (! $user || (int) $user->role !== User::ROLE_MANAGER || ! $branch) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $requestedBranch = $request->input('car_wash_id', $request->input('branch_id'));
        if ($requestedBranch !== null && (int) $requestedBranch !== (int) $branch->id) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $userPackage = UserPackage::query()->whereKey($id)->first();
        if (! $userPackage) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $start = \Carbon\Carbon::parse($userPackage->start_date);
        $end = \Carbon\Carbon::parse($userPackage->end_date);
        $active = now()->betweenIncluded($start, $end)
            && (int) $userPackage->used_washes < (int) $userPackage->number_of_washes;

        if (! $active) {
            return response()->json(['success' => false, 'error' => 'Package is not active'], 422);
        }

        $userPackage->used_washes = (int) $userPackage->used_washes + 1;
        $userPackage->save();

        $userPackage->user?->sendPush([
            'en' => 'The wash has begun',
            'ru' => 'Мойка началась',
            'ka' => 'სარეცხი დაიწყო',
        ], [
            'en' => 'The washing process has begun',
            'ru' => 'Процесс мойки начат',
            'ka' => 'სარეცხი პროცესი დაიწყო',
        ]);

        return response()->json([
            'success' => true,
            'package' => [
                'id' => $userPackage->id,
                'used_washes' => (int) $userPackage->used_washes,
                'number_of_washes' => (int) $userPackage->number_of_washes,
            ],
        ]);
    }
}
