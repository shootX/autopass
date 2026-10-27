<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CorporateWash;
use App\Models\FleetCar;
use App\Models\User;
use App\Services\CorporateQr;
use App\Services\CorporateWashes;
use App\Support\ApproveGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** A company car at the wash: the driver's QR and the manager's approval that records the wash. */
class CorporateWashController extends Controller
{
    /** GET /mycars/{carid}/company-qr: a rotating SVG code; X-QR-Expires says when to fetch a new one. */
    public function qr(Request $request, int $carid)
    {
        $car = CorporateQr::driverCar($request->user(), $carid);
        if (! $car) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
        if ($car->client?->ban) {
            return response()->json(['success' => false, 'error' => __('corporate.reason_company_blocked'), 'reason' => 'company_blocked'], 403);
        }
        if (! $car->bodyConfirmed()) {
            return response()->json(['success' => false, 'error' => __('corporate.reason_body_unconfirmed'), 'reason' => 'body_unconfirmed'], 403);
        }

        $svg = QrCode::format('svg')->size(300)->generate(CorporateQr::issue($car));

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-store')
            ->header('X-QR-Expires', (string) $car->qr_expires_at->timestamp);
    }

    /** POST /corporate-wash/{id}/approve with the approve_token from checkqr. */
    public function approve(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || (int) $user->role !== User::ROLE_MANAGER) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $input = Validator::make($request->all(), [
            'approve_token' => ['required', 'string', 'max:128'],
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

        $car = FleetCar::query()->find($id);
        if (! $car) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        // Same rule as packages: a walk-in is fine, a booking today at another branch is not.
        $todays = Appointment::query()
            ->where('car_id', $car->user_car_id)
            ->whereDate('date', now()->toDateString())
            ->get(['id', 'car_wash_id']);
        $here = $todays->firstWhere('car_wash_id', $washId);
        if (! $here && $todays->isNotEmpty()) {
            return response()->json(['success' => false, 'error' => 'Appointment belongs to another branch'], 403);
        }

        $grant = ApproveGrant::consume(CorporateQr::GRANT_SCOPE, (string) $request->input('approve_token'));
        if ($grant === 'busy') {
            return response()->json(['success' => false, 'error' => 'Approval already in progress'], 409);
        }
        if (! is_array($grant)
            || (int) ($grant['manager_id'] ?? 0) !== (int) $user->id
            || (int) ($grant['fleet_car_id'] ?? 0) !== (int) $car->id) {
            return response()->json(['success' => false, 'error' => __('corporate.scan_again')], 403);
        }

        // The booking is linked to its first wash only; later washes count as walk-ins.
        $bookingUnused = $here && ! CorporateWash::query()->where('appointment_id', $here->id)->exists();
        $done = CorporateWashes::wash($car, [
            'car_wash_id' => (int) $washId,
            'manager_id' => (int) $user->id,
            'appointment_id' => $bookingUnused ? (int) $here->id : null,
            'walk_in' => $here === null,
        ]);
        if ($done['result'] !== 'ok') {
            return response()->json([
                'success' => false,
                'error' => __('corporate.reason_'.$done['result']),
                'reason' => $done['result'],
            ], 422);
        }

        $car->user?->sendPush([
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
            'corporate' => [
                'id' => (int) $car->id,
                'mode' => $done['mode'],
                'washes_left' => $done['washes_left'],
                'company_washes' => $done['company_washes'],
                // Discount mode: what was charged; the driver pays to_pay on site.
                'discount' => $done['discount'],
            ],
        ]);
    }
}
