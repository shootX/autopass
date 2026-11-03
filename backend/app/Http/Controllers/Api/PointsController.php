<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Services\Payments\FlittCheckout;
use App\Services\Points\PointsPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PointsController extends Controller
{
    /** GET /api/points/price: the price of one point, its discount and the amounts allowed. */
    public function price(): JsonResponse
    {
        return response()->json(['success' => true, 'price' => PointsPricing::card()]);
    }

    /** POST /api/points/quote {amount, promo_code?}: what the purchase would cost, without paying. */
    public function quote(Request $request): JsonResponse
    {
        $checked = $this->checked($request);
        if ($checked instanceof JsonResponse) {
            return $checked;
        }

        return response()->json(['success' => true, 'quote' => PointsPricing::quote(...$checked)]);
    }

    public function buy(Request $request): JsonResponse
    {
        $checked = $this->checked($request);
        if ($checked instanceof JsonResponse) {
            return $checked;
        }
        [$points, $promo] = $checked;
        $user = $request->user();

        // A discount code is reserved by the payment that uses it, under a lock on the code: one open payment
        // per user and no more open payments than uses left, so paying several pages can't stretch the limit.
        $payment = DB::transaction(function () use ($points, $promo, $user) {
            if ($promo) {
                $promo = PromoCode::query()->lockForUpdate()->findOrFail($promo->id);
                if ($problem = $promo->problem($user)) {
                    return $problem;
                }
                // Only a page that still works is handed back; a closed one waits to be settled.
                $open = $promo->holds()->where('user_id', $user->id)
                    ->where('created_at', '>', now()->subMinutes(TbcPayment::CHECKOUT_MINUTES))->latest('id')->first();
                if ($open) {
                    // The same purchase again (the page was closed): the same page. Another amount waits.
                    return (int) ($open->payload['points'] ?? 0) === $points ? $open : 'promo_pending';
                }
            }
            $quote = PointsPricing::quote($points, $promo);

            return TbcPayment::create([
                'merchant_payment_id' => 'P'.strtoupper(Str::random(16)),
                'user_id' => $user->id,
                'type' => 'points',
                'amount' => $quote['total'],
                'currency' => 'GEL',
                'status' => 'pending',
                'payload' => [
                    'points' => $points,
                    'promo_code_id' => $quote['promo_code'] ? $promo->id : null,
                    'quote' => $quote,
                ],
            ]);
        });
        if (is_string($payment)) {
            return response()->json(['success' => false, 'error' => __('points.'.$payment), 'code' => $payment], 422);
        }

        $url = $payment->payload['checkout_url'] ?? null;
        if (! is_string($url) || $url === '') {
            $url = app(FlittCheckout::class)->checkoutUrl($payment, 'Autopass points');
            $payment->update(['payload' => $payment->payload + ['checkout_url' => $url]]);
        }

        return response()->json(['success' => true, 'url' => $url, 'quote' => $payment->payload['quote']]);
    }

    /** [points, promo code or null] or the error answer. */
    private function checked(Request $request): array|JsonResponse
    {
        [$min, $max] = PointsPricing::limits();
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:'.$min.'|max:'.$max,
            'promo_code' => 'nullable|string|max:32',
        ], [], ['amount' => __('points.amount')]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $promo = null;
        if (filled($request->input('promo_code'))) {
            $promo = PromoCode::query()->where('code', PromoCode::normalize((string) $request->input('promo_code')))->first();
            $problem = ! $promo ? 'promo_unknown'
                : ($promo->kind !== PromoCode::KIND_DISCOUNT ? 'promo_not_discount' : $promo->problem($request->user()));
            if ($problem) {
                return response()->json(['success' => false, 'error' => __('points.'.$problem), 'code' => $problem], 422);
            }
        }

        return [(int) $request->input('amount'), $promo];
    }
}
