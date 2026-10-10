<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TbcPayment;
use App\Services\Payments\FlittCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PointsController extends Controller
{
    public function buy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ], [
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.min' => 'The amount must be at least 1.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()]);
        }

        $points = (int) $request->amount;
        if ($points < 1) {
            return response()->json(['success' => false, 'error' => 'The amount must be at least 1.']);
        }

        $payment = TbcPayment::create([
            'merchant_payment_id' => 'P'.strtoupper(Str::random(16)),
            'user_id' => auth()->id(),
            'type' => 'points',
            'amount' => $points,
            'currency' => 'GEL',
            'status' => 'pending',
            'payload' => ['points' => $points],
        ]);

        $url = app(FlittCheckout::class)->checkoutUrl($payment, 'Autopass points');

        return response()->json(['success' => true, 'url' => $url]);
    }
}
