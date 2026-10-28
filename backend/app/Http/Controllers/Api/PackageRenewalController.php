<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageRenewalController extends Controller
{
    /** POST /api/packages/{userPackage}/renewal {renewal: bool}: the driver's own switch. */
    public function update(Request $request, UserPackage $userPackage): JsonResponse
    {
        abort_unless(UserPackage::renewalEnabled(), 404);
        if ((int) $userPackage->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }
        $data = $request->validate(['renewal' => 'required|boolean']);

        if ($data['renewal'] && ! filled($userPackage->rectoken)) {
            return response()->json(['success' => false, 'error' => __('points.renewal_no_card'), 'code' => 'renewal_no_card'], 422);
        }

        $userPackage->forceFill(['auto_renew' => (bool) $data['renewal']])->save();

        return response()->json(['success' => true, 'renewal' => $userPackage->renews()]);
    }
}
