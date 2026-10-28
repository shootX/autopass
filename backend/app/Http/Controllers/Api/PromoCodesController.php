<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\PromoCodeUse;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoCodesController extends Controller
{
    /**
     * POST /api/promo-codes/activate {code}. A points code credits its points now, once per user.
     * A discount code is only checked: the app sends it with the next points purchase.
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:32']);
        $code = PromoCode::normalize((string) $request->input('code'));
        $user = $request->user();

        $result = DB::transaction(function () use ($code, $user) {
            $promo = PromoCode::query()->where('code', $code)->lockForUpdate()->first();
            if (! $promo) {
                return 'promo_unknown';
            }
            if ($problem = $promo->problem($user)) {
                return $problem;
            }
            if ($promo->kind === PromoCode::KIND_DISCOUNT) {
                return $promo;
            }

            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->points += $promo->value;
            $locked->save();
            PromoCodeUse::query()->create(['promo_code_id' => $promo->id, 'user_id' => $user->id, 'points' => $promo->value]);
            $promo->increment('used_count');
            Transaction::create([
                'user_id' => $user->id,
                'amount' => $promo->value,
                'type' => 'income',
                'system' => 'Promo',
                'status' => true,
                'comment' => 'Promo '.$promo->code,
            ]);

            return $promo;
        });

        if (is_string($result)) {
            return response()->json(['success' => false, 'error' => __('points.'.$result), 'code' => $result], 422);
        }

        $discount = $result->kind === PromoCode::KIND_DISCOUNT;

        return response()->json([
            'success' => true,
            'kind' => $result->kind,
            'code' => $result->code,
            'value' => $result->value,
            'points' => (int) $user->fresh()->points,
            'message' => __($discount ? 'points.promo_discount_ready' : 'points.promo_points_added', ['value' => $result->value]),
        ]);
    }
}
