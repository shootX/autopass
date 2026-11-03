<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FleetInvites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A company named a plate the driver already has in the app: the driver says yes or no before their car (and the
 * packages on it) joins the company. The waiting invites come with /me as `fleet_invites`.
 */
class FleetConsentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'invites' => FleetInvites::awaitingFor($request->user())]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $car = FleetInvites::awaiting($request->user(), $id);
        if (! $car) {
            return response()->json(['success' => false, 'error' => __('corporate.invite_gone'), 'error_code' => 'invite_gone'], 404);
        }

        $error = FleetInvites::accept($car, $request->user());
        if ($error === 'invite_gone') {
            return response()->json(['success' => false, 'error' => __('corporate.invite_gone'), 'error_code' => 'invite_gone'], 404);
        }
        if ($error !== null) {
            $key = $error === 'body_conflict' ? 'corporate.invite_body_conflict' : 'corporate.invite_failed';

            return response()->json(['success' => false, 'error' => __($key), 'error_code' => $error], 422);
        }

        return response()->json(['success' => true, 'message' => __('corporate.invite_accepted')]);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $car = FleetInvites::awaiting($request->user(), $id);
        if (! $car) {
            return response()->json(['success' => false, 'error' => __('corporate.invite_gone'), 'error_code' => 'invite_gone'], 404);
        }

        if (! FleetInvites::decline($car, $request->user())) {
            return response()->json(['success' => false, 'error' => __('corporate.invite_gone'), 'error_code' => 'invite_gone'], 404);
        }

        return response()->json(['success' => true, 'message' => __('corporate.invite_declined')]);
    }
}
