<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushDeviceController extends Controller
{
    /**
     * POST /api/device/apns {token, sandbox?}: the iOS app sends its raw APNs token; the server
     * registers it with OneSignal (no SDK in the app) and keeps the subscription id like /device/push.
     */
    public function apns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[0-9a-fA-F]{32,200}$/'],
            'sandbox' => ['sometimes', 'boolean'],
        ]);
        $appId = (string) config('OneSignal.app_id');
        $key = (string) config('OneSignal.rest_api_key');
        if ($appId === '' || $key === '') {
            return response()->json(['success' => false, 'error' => __('points.push_unavailable')], 503);
        }

        $user = $request->user();
        $token = strtolower($data['token']);
        $subscription = ['type' => 'iOSPush', 'token' => $token, 'enabled' => true];
        if ($request->boolean('sandbox')) {
            $subscription['test_type'] = 1;
        }

        try {
            $response = Http::withHeaders(['Authorization' => 'Key '.$key])->timeout(10)
                ->post("https://api.onesignal.com/apps/{$appId}/users", [
                    'identity' => ['external_id' => (string) $user->id],
                    'subscriptions' => [$subscription],
                ]);
        } catch (\Throwable $e) {
            Log::warning('OneSignal APNs registration failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => __('points.push_unavailable')], 502);
        }

        $id = collect((array) $response->json('subscriptions'))
            ->first(fn ($row) => strtolower((string) ($row['token'] ?? '')) === $token)['id'] ?? null;
        if (! $response->successful() || ! is_string($id) || $id === '') {
            Log::warning('OneSignal APNs registration refused', ['status' => $response->status()]);

            return response()->json(['success' => false, 'error' => __('points.push_unavailable')], 502);
        }

        UserPushToken::query()->where('push_id', $id)->delete();
        $user->pushTokens()->delete();
        $user->pushTokens()->create(['push_id' => $id]);

        return response()->json(['success' => true]);
    }
}
