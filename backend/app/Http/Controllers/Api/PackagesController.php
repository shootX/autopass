<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\FleetCar;
use App\Models\Package;
use App\Models\PackageWash;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\Payments\PackagePurchase;
use App\Support\QrCodeId;
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

            // Discount packages only for an app that asks for them: an older one would sell them as washes.
            $packages = Package::where('car_type', $carType)
                ->when(! $request->boolean('discount'), fn ($q) => $q->where('type', Package::TYPE_WASHES))
                ->with('prices')->get();
            if ($packages->isEmpty()) {
                return response()->json(['success' => false, 'error' => 'პაკეტი ვერ მოიძებნა']);
            }

            $packages = $packages->map(function (Package $package) {
                return [
                    'id' => $package->id,
                    'type' => $package->type,
                    'car_type' => $package->car_type,
                    'washes' => $package->count_washes,
                    'prices' => $package->prices,
                ] + ($package->isDiscount() ? ['discount' => [
                    'full_price' => (float) $package->full_price,
                    'percent' => (int) $package->percent,
                    'to_pay' => (float) $package->toPay(),
                    // Unlimited for the 30 days, else `washes` washes in them.
                    'unlimited' => (int) $package->count_washes === 0,
                    'days' => Package::DISCOUNT_DAYS,
                ]] : []);
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
            // package_id names any package; without it, number_of_washes and sub_term name a washes package.
            'package_id' => 'nullable|integer',
            'number_of_washes' => 'required_without:package_id|integer|min:1',
            'renewal' => 'required|boolean',
            'sub_term' => 'required_without:package_id|integer|min:1',
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
            // A company car handed to its next driver takes its running packages along: they leave this list
            // at once, before that driver signs in and takes them.
            $leaving = UserCar::onlyTrashed()
                ->whereIn('id', FleetCar::query()->whereNotNull('handover_user_car_id')->select('handover_user_car_id'))
                ->select('id');
            $packages = $user->packages()->with(['package', 'car'])
                ->where(fn ($query) => $query->where('end_date', '<=', now())->orWhereNotIn('user_car_id', $leaving))
                ->withSum('washes as saved_sum', DB::raw('full_price - to_pay'))->get();

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
                    // The exact moment it stops working, with its zone: the app compares this, not the day.
                    'ends_at' => $package->end_date ? Carbon::parse($package->end_date)->toIso8601String() : null,
                    'number_of_washes' => $package->number_of_washes,
                    'used_washes' => $package->used_washes,
                    'renewal' => $package->renews(),
                    // A card is kept for this package, so the driver may switch renewal on or off.
                    'renewal_available' => UserPackage::renewalEnabled() && filled($package->rectoken),
                    'type' => $package->isDiscount() ? Package::TYPE_DISCOUNT : Package::TYPE_WASHES,
                    'unlimited' => (bool) $package->unlimited,
                    // Discount packages: the terms bought, and what the driver saved at its washes so far.
                    'discount' => $package->isDiscount() ? [
                        'full_price' => (float) $package->full_price,
                        'percent' => (int) $package->discount_percent,
                        'to_pay' => (float) $package->toPay(),
                        'saved' => round((float) $package->saved_sum, 2),
                    ] : null,
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
            if ($package->car?->trashed()) {
                // The car left this driver's app (a company gave it to its next driver): its packages go along.
                return response()->json(['success' => false, 'error' => __('cars.car_not_found')], 404);
            }
            if ($bodyProblem = $package->bodyBlocker()) {
                return response()->json(['success' => false, 'error' => __('cars.'.$bodyProblem), 'error_code' => $bodyProblem], 422);
            }
            // No code for a package a wash would be refused on: past its exact end time, not started, used up.
            if ($package->end_date && now()->greaterThan($package->end_date)) {
                return response()->json(['success' => false, 'error' => __('cars.package_expired'), 'error_code' => 'package_expired'], 422);
            }
            if ($package->start_date && now()->lessThan(Carbon::parse($package->start_date)->startOfDay())) {
                return response()->json(['success' => false, 'error' => __('cars.package_not_started'), 'error_code' => 'package_not_started'], 422);
            }
            if (! $package->hasWashesLeft()) {
                return response()->json(['success' => false, 'error' => __('cars.package_used_up'), 'error_code' => 'package_used_up'], 422);
            }

            // A fresh code replaces the previous one and works for a few minutes only, like a company car's.
            $package->update([
                'qr_code' => Str::random(16),
                'qr_expires_at' => now()->addSeconds(UserPackage::QR_TTL_SECONDS),
            ]);

            $qrCodeSvg = QrCode::format('svg')->size(300)->generate($package->qr_code);

            return response($qrCodeSvg, 200)
                ->header('Content-Type', 'image/svg+xml')
                ->header('X-QR-Expires', (string) $package->qr_expires_at->timestamp)
                ->header('X-QR-Id', QrCodeId::of($package->qr_code));
        } catch (\Exception $e) {
            Log::warning('package_request_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'Request failed']);
        }
    }

    /**
     * Whether the package code the app shows still works: a wash, a newer code or its expiry ends it. `code` is the
     * X-QR-Id the app got with it; a newer code taken on another phone ends this one too.
     */
    public function qrLive(Request $request, int $id)
    {
        $package = $request->user()->packages()->whereKey($id)->first(['id', 'qr_code', 'qr_expires_at']);
        if (! $package) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
        $running = $package->qr_expires_at !== null && $package->qr_expires_at->isFuture();
        $live = $running && QrCodeId::shown($package->qr_code, $request->query('code'));

        // replaced: another phone of this driver took a newer code. The app says so instead of taking it back,
        // which would end that phone's code in turn.
        return response()->json(['success' => true, 'live' => $live, 'replaced' => $running && ! $live]);
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
            // Only a booking still on: one cancelled, done or expired at another branch blocks nothing.
            ->whereIn('approved', AppointmentStatus::active())
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
        if (! is_array($presented)) {
            return response()->json(['success' => false, 'error' => 'Scan the package QR code'], 403);
        }
        $qr = (string) ($presented['qr'] ?? '');

        if ($userPackage->start_date && now()->lessThan(Carbon::parse($userPackage->start_date)->startOfDay())) {
            return response()->json(['success' => false, 'error' => 'Package is not active'], 422);
        }
        if ($userPackage->end_date && now()->greaterThan($userPackage->end_date)) {
            return response()->json(['success' => false, 'error' => 'Package expired'], 422);
        }
        if (! $userPackage->hasWashesLeft()) {
            return response()->json(['success' => false, 'error' => 'No washes left'], 422);
        }
        if ($bodyProblem = $userPackage->bodyBlocker()) {
            return response()->json(['success' => false, 'error' => __('cars.'.$bodyProblem), 'error_code' => $bodyProblem], 422);
        }

        $approved = DB::transaction(function () use ($userPackage, $washId, $user, $hereAppointment, $qr) {
            $locked = UserPackage::query()->whereKey($userPackage->id)->lockForUpdate()->first();
            // Checked under the row lock, with the deduction: one scanned code gives one wash, however many
            // tokens were taken from it, and parallel approvals can't both see it live.
            if (! $locked || ! $locked->qrIsLive($qr)) {
                return 'scan_again';
            }
            if (! $locked->hasWashesLeft()) {
                return 'no_washes';
            }
            // The booking is linked to its first wash only; later washes count as walk-ins.
            $bookingUnused = $hereAppointment
                && ! PackageWash::query()->where('appointment_id', $hereAppointment->id)->exists();
            $locked->increment('used_washes');
            // The scanned code is spent: the next wash needs a new QR from the driver's phone.
            $locked->forceFill(['qr_code' => Str::random(16), 'qr_expires_at' => null])->save();
            PackageWash::create([
                'user_package_id' => $userPackage->id,
                'car_wash_id' => $washId,
                'manager_id' => $user->id,
                'appointment_id' => $bookingUnused ? $hereAppointment->id : null,
                'walk_in' => $hereAppointment === null,
                // A discount wash keeps what the driver paid on site.
                'full_price' => $locked->isDiscount() ? $locked->full_price : null,
                'discount_percent' => $locked->discount_percent,
                'to_pay' => $locked->toPay(),
            ]);
            Appointment::completeByWash($bookingUnused ? (int) $hereAppointment->id : null);

            return 'ok';
        });

        if ($approved === 'scan_again') {
            return response()->json(['success' => false, 'error' => __('corporate.scan_again'), 'error_code' => 'scan_again'], 403);
        }
        if ($approved !== 'ok') {
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

        return response()->json(['success' => true, 'package' => $userPackage->fresh()->makeHidden(['qr_code', 'qr_expires_at']),
            'to_pay' => $userPackage->isDiscount() ? (float) $userPackage->toPay() : null]);
    }

    /** Consumes the checkqr token (qr_code is ignored). @return array<string, mixed>|'busy'|'no' the grant when it is this manager's for this package */
    private function presentedPackage(Request $request, UserPackage $userPackage, int $managerId): array|string
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
            && (int) ($grant['package_id'] ?? 0) === (int) $userPackage->id ? $grant : 'no';
    }
}
