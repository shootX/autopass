<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbcPayment;
use App\Models\User;
use App\Support\AdminAudit;
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

        $max = (int) config('security.test_payment_max_amount', 100);
        $raw = $request->input('amount', '1');
        if (! is_scalar($raw) || ! ctype_digit((string) $raw) || (int) $raw < 1 || (int) $raw > $max) {
            return response()->json(['success' => false, 'error' => 'The amount must be between 1 and '.$max.'.'], 422);
        }
        $points = (int) $raw;

        // Daily budget per user across all sandbox payments that weren't declined.
        $daily = (int) config('security.test_payment_daily_max', 300);
        $today = (int) TbcPayment::query()
            ->where('user_id', $user->id)
            ->where('test_mode', true)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotIn('status', ['Failed', 'Expired'])
            ->sum('amount');
        if ($today + $points > $daily) {
            return response()->json(['success' => false, 'error' => 'Daily test payment limit reached ('.$daily.').'], 422);
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

        if ((int) $user->role === User::ROLE_ADMIN) {
            AdminAudit::record('tbc_payment', (int) $payment->id, 'testpay', 'amount', null, (string) $points, (int) $user->id);
        }

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
