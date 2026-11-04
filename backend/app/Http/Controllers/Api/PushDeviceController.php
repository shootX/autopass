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
     * to Apple itself, in the app's language; every signed-in phone of a user, and a token belongs to one user.
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

        // A phone belongs to the account signed in on it now: another account's copy of this token goes.
        UserPushToken::query()->where('push_id', $token)->where('user_id', '!=', $user->id)->delete();
        // Every phone the account is signed in on gets its pushes (owner's rule, 2026-11-04).
        $user->pushTokens()->updateOrCreate(['push_id' => $token], [
            'kind' => UserPushToken::KIND_APNS,
            'sandbox' => $request->boolean('sandbox'),
            'lang' => $data['lang'] ?? null,
        ])->touch();
        $stale = $user->pushTokens()->orderByDesc('updated_at')->orderByDesc('id')->skip(UserPushToken::MAX_PER_USER)->take(100)->pluck('id');
        if ($stale->isNotEmpty()) {
            UserPushToken::query()->whereKey($stale)->delete();
        }

        return response()->json(['success' => true]);
    }
}
