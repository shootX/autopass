<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    /**
     * POST /api/device/apns {token, sandbox?, lang?}: the iOS app's raw APNs token. The server sends
     * to Apple itself, in the app's language; one device per user, and a token belongs to one user.
     */
    public function apns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[0-9a-fA-F]{32,200}$/'],
            'sandbox' => ['sometimes', 'boolean'],
            'lang' => ['sometimes', 'nullable', 'in:ka,en,ru'],
        ]);
        $user = $request->user();
        $token = strtolower($data['token']);

        UserPushToken::query()->where('push_id', $token)->delete();
        $user->pushTokens()->delete();
        $user->pushTokens()->create([
            'push_id' => $token,
            'kind' => UserPushToken::KIND_APNS,
            'sandbox' => $request->boolean('sandbox'),
            'lang' => $data['lang'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }
}
