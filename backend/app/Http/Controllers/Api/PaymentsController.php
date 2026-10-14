<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbcPayment;
use App\Support\TestPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    public function testpay(Request $request)
    {
        if (! TestPayments::available()) {
            abort(404);
        }

        $user = $request->user();
        if (! TestPayments::allowed($user)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $points = (int) $request->input('amount', 1);
        if ($points < 1) {
            return response()->json(['success' => false, 'error' => 'The amount must be at least 1.'], 422);
        }

        $access = bin2hex(random_bytes(32));
        $payment = TbcPayment::query()->create([
            'merchant_payment_id' => 'T'.strtoupper(Str::random(16)),
            'user_id' => $user->id,
            'type' => 'points',
            'amount' => $points,
            'currency' => 'GEL',
            'status' => 'pending',
            'test_mode' => true,
            'payload' => [
                'points' => $points,
                'test' => true,
            ],
            'access_hash' => hash('sha256', $access),
            'access_expires_at' => now()->addMinutes((int) config('security.payment_access_minutes')),
        ]);

        $url = route('payment.sandbox', [
            'order' => $payment->merchant_payment_id,
            'access' => $access,
        ]);

        return response()->json([
            'success' => true,
            'test_mode' => true,
            'url' => $url,
            'order' => $payment->merchant_payment_id,
        ]);
    }
}
