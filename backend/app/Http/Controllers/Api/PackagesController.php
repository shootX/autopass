<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Package;
use App\Models\PackageWash;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\Payments\PackagePurchase;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            if (! $userCar) {
                return response()->json(['success' => false, 'error' => 'ავტომობილი ვერ მოიძებნა']);
            }

            $carType = $userCar->carType();
            if ($carType === null) {
                // Packages are priced by the body type; none is offered until it is confirmed.
                return response()->json(['success' => false, 'error' => __('cars.body_unconfirmed'), 'error_code' => 'body_unconfirmed',
                    'body_status' => $userCar->body_status], 422);
            }

            $packages = Package::where('car_type', $carType)->get();
            if ($packages->isEmpty()) {
                return response()->json(['success' => false, 'error' => 'პაკეტი ვერ მოიძებნა']);
            }

            $packages = $packages->map(function ($package) {
                return [
                    'id' => $package->id,
                    'car_type' => $package->car_type,
                    'washes' => $package->count_washes,
                    'prices' => $package->prices,
                ];
            });

            return response()->json(['success' => true, 'packages' => $packages]);
        } catch (\Exception $e) {
            Log::warning('package_request_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'Request failed']);
        }
    }

    public function buyPackage(Request $request)
    {
        $data = $request->validate([
            'car_id' => 'required|integer|exists:user_cars,id',
            'number_of_washes' => 'required|integer|min:1',
            'renewal' => 'required|boolean',
            'sub_term' => 'required|integer|min:1',
        ]);
        $car = $request->user()->cars()->find($data['car_id']);
        if ($car && ! $car->bodyConfirmed()) {
            return response()->json(['success' => false, 'error' => __('cars.body_unconfirmed'), 'error_code' => 'body_unconfirmed'], 422);
        }
        try {
            $url = app(PackagePurchase::class)->checkout($request->user(), $data);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'error' => collect($e->errors())->flatten()->first()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        } catch (LockTimeoutException $e) {
            return response()->json(['success' => false, 'error' => __('booking.payment_in_progress')], 409);
        } catch (\Throwable $e) {
            Log::warning('package_checkout_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => __('booking.checkout_failed')], 503);
        }

        return response()->json(['success' => true, 'url' => $url]);
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
                    'renewal' => $package->rectoken ? true : false,
                ];
            });

            return response()->json(['success' => true, 'packages' => $packages]);
        } catch (\Exception $e) {
            Log::warning('package_request_failed', ['exception' => $e::class]);

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
            if (! $package->bodyAllowsWash()) {
                return response()->json(['success' => false, 'error' => __('cars.body_unconfirmed'), 'error_code' => 'body_unconfirmed'], 422);
            }

            $package->update([
                'qr_code' => Str::random(16),
            ]);

            $qrCodeSvg = QrCode::format('svg')->size(300)->generate($package->qr_code);

            return response($qrCodeSvg, 200)
                ->header('Content-Type', 'image/svg+xml');
        } catch (\Exception $e) {
            Log::warning('package_request_failed', ['exception' => $e::class]);

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

        if (! $user || (int) $user->role !== User::ROLE_MANAGER) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $input = Validator::make($request->all(), [
            'qr_code' => ['nullable', 'string', 'max:128'],
            'approve_token' => ['nullable', 'string', 'max:128'],
            'car_wash_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);
        if ($input->fails()) {
            return response()->json(['success' => false, 'error' => $input->errors()], 422);
        }

        $washId = $user->washing?->id;
        if (! $washId) {
            return response()->json(['success' => false, 'error' => 'Manager has no branch'], 403);
        }

        $requestedBranch = $request->input('car_wash_id', $request->input('branch_id'));
        if ($requestedBranch !== null && (int) $requestedBranch !== (int) $washId) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $userPackage = UserPackage::find($id);
        if (! $userPackage) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        // Branch check. Walk-ins (no booking today) are allowed and recorded on this branch;
        // a booking for this car today at another branch only can't be approved here.
        $todays = Appointment::query()
            ->where('car_id', $userPackage->user_car_id)
            ->whereDate('date', now()->toDateString())
            ->get(['id', 'car_wash_id']);
        $hereAppointment = $todays->firstWhere('car_wash_id', $washId);
        if (! $hereAppointment && $todays->isNotEmpty()) {
            return response()->json(['success' => false, 'error' => 'Appointment belongs to another branch'], 403);
        }

        // Only the one-time token from GET /checkqr/{qr} approves a wash: a static QR (or a
        // screenshot of it) is never enough on its own, and every token deducts at most once.
        // A per-package lock makes parallel approvals of one package wait their turn (409).
        $packageLock = Cache::lock('package-approve-pkg:'.$userPackage->id, 15);
        if (! $packageLock->get()) {
            return response()->json(['success' => false, 'error' => 'Approval already in progress'], 409);
        }
        try {
            return $this->approveLocked($request, $userPackage, $user, (int) $washId, $hereAppointment);
        } finally {
            $packageLock->release();
        }
    }

    private function approveLocked(Request $request, UserPackage $userPackage, User $user, int $washId, ?Appointment $hereAppointment)
    {
        $presented = $this->presentedPackage($request, $userPackage, (int) $user->id);
        if ($presented === 'busy') {
            return response()->json(['success' => false, 'error' => 'Approval already in progress'], 409);
        }
        if ($presented !== 'ok') {
            return response()->json(['success' => false, 'error' => 'Scan the package QR code'], 403);
        }

        if ($userPackage->start_date && now()->lessThan(Carbon::parse($userPackage->start_date)->startOfDay())) {
            return response()->json(['success' => false, 'error' => 'Package is not active'], 422);
        }
        if ($userPackage->end_date && now()->greaterThan($userPackage->end_date)) {
            return response()->json(['success' => false, 'error' => 'Package expired'], 422);
        }
        if ((int) $userPackage->used_washes >= (int) $userPackage->number_of_washes) {
            return response()->json(['success' => false, 'error' => 'No washes left'], 422);
        }
        if (! $userPackage->bodyAllowsWash()) {
            return response()->json(['success' => false, 'error' => __('cars.body_unconfirmed'), 'error_code' => 'body_unconfirmed'], 422);
        }

        $approved = DB::transaction(function () use ($userPackage, $washId, $user, $hereAppointment) {
            $locked = UserPackage::query()->whereKey($userPackage->id)->lockForUpdate()->first();
            if (! $locked || (int) $locked->used_washes >= (int) $locked->number_of_washes) {
                return false;
            }
            // The booking is linked to its first wash only; later washes count as walk-ins.
            $bookingUnused = $hereAppointment
                && ! PackageWash::query()->where('appointment_id', $hereAppointment->id)->exists();
            $locked->increment('used_washes');
            PackageWash::create([
                'user_package_id' => $userPackage->id,
                'car_wash_id' => $washId,
                'manager_id' => $user->id,
                'appointment_id' => $bookingUnused ? $hereAppointment->id : null,
                'walk_in' => $hereAppointment === null,
            ]);

            return true;
        });

        if (! $approved) {
            return response()->json(['success' => false, 'error' => 'No washes left'], 422);
        }

        $userPackage->user?->sendPush([
            'en' => 'The wash has begun',
            'ru' => 'Мойка началась',
            'ka' => 'სარეცხი დაიწყო',
        ], [
            'en' => 'The washing process has begun',
            'ru' => 'Процесс мойки начат',
            'ka' => 'სარეცხი პროცესი დაიწყო',
        ]);

        return response()->json(['success' => true, 'package' => $userPackage->fresh()]);
    }

    /** Consumes the checkqr token (qr_code is ignored). @return 'ok'|'busy'|'no' */
    private function presentedPackage(Request $request, UserPackage $userPackage, int $managerId): string
    {
        $token = (string) $request->input('approve_token', '');
        if ($token === '') {
            return 'no';
        }
        $key = 'package-approve:'.hash('sha256', $token);

        // Single use under concurrency: only the request holding the lock may read and delete the
        // grant; a parallel request gets 'busy' and the grant is gone once the holder is done.
        $lock = Cache::lock($key.':lock', 10);
        if (! $lock->get()) {
            return 'busy';
        }
        try {
            $grant = Cache::get($key);
            Cache::forget($key);
        } finally {
            $lock->release();
        }

        return is_array($grant)
            && (int) ($grant['manager_id'] ?? 0) === $managerId
            && (int) ($grant['package_id'] ?? 0) === (int) $userPackage->id ? 'ok' : 'no';
    }
}
