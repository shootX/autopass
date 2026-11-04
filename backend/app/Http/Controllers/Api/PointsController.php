<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\TbcPayment;
use App\Models\User;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use App\Services\Points\PointsPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

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
        if ($promo) {
            $this->askAboutClosedPages($promo, $user);
        }

        // A discount code is reserved by the payment that uses it, under a lock on the code: one open payment
        // per user and no more open payments than uses left, so paying several pages can't stretch the limit.
        $payment = DB::transaction(function () use ($points, $promo, $user) {
            if ($promo) {
                $promo = PromoCode::query()->lockForUpdate()->findOrFail($promo->id);
                if ($problem = $promo->problem($user)) {
                    return $problem;
                }
                $open = $promo->holds()->where('user_id', $user->id)->latest('id')->first();
                if ($open && $open->created_at <= now()->subMinutes(TbcPayment::CHECKOUT_MINUTES)) {
                    // A closed page Flitt couldn't tell about yet: paid in its last minute, it already has the
                    // discount, so a new page waits for the answer (payments:reconcile asks every few minutes).
                    return 'promo_checking';
                }
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

        // One request at a time makes the page: a failure below can't close a page another request just handed out.
        $url = Cache::lock('points-checkout:'.$payment->id, 60)->block(10, function () use ($payment) {
            $current = $payment->fresh();
            $url = $current->payload['checkout_url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
            if ($current->status !== 'pending') {
                return null;
            }
            try {
                $url = app(FlittCheckout::class)->checkoutUrl($current, 'Autopass points');
                $current->update(['payload' => $current->payload + ['checkout_url' => $url]]);
            } catch (Throwable $e) {
                // No link reached the driver, so nothing can be paid: the code's use is let go at once.
                report($e);
                app(SettleTbcPayment::class)->closeUnopened($current);

                return null;
            }

            return $url;
        });
        if ($url === null) {
            return response()->json(['success' => false, 'error' => __('points.checkout_failed'), 'code' => 'checkout_failed'], 503);
        }

        return response()->json(['success' => true, 'url' => $url, 'quote' => $payment->payload['quote']]);
    }

    /**
     * This user's pages with the code that closed without an answer: Flitt is asked now, before a new page, so a
     * page paid in its last minute counts the code as used instead of giving the discount twice.
     */
    private function askAboutClosedPages(PromoCode $promo, User $user): void
    {
        $promo->holds()->where('user_id', $user->id)
            ->where('created_at', '<=', now()->subMinutes(TbcPayment::CHECKOUT_MINUTES))
            ->get()
            ->each(function (TbcPayment $payment) {
                try {
                    // A page Flitt never made is let go here, so the code is free again at once.
                    app(SettleTbcPayment::class)->fromFlittOrUnopened($payment, app(FlittCheckout::class));
                } catch (Throwable $e) {
                    // Still pending: the new page waits, payments:reconcile asks again.
                    Log::warning('promo_hold_check_failed', ['payment_id' => $payment->id, 'exception' => $e::class]);
                }
            });
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
