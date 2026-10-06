<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Flitt\Checkout;
use Flitt\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        if (! filled(env('FLITT_PAY_NUMBER')) || ! filled(env('FLITT_PAYMENT_KEY'))) {
            if (! app()->environment('local')) {
                return response()->json(['success' => false, 'error' => 'Payment provider is not configured'], 500);
            }

            $amount = (int) $request->amount;

            DB::transaction(function () use ($amount) {
                $user = auth()->user();
                $user->points += $amount;
                $user->save();

                Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'income',
                    'system' => 'local',
                    'status' => true,
                    'comment' => 'Points',
                ]);
            });

            $origin = rtrim((string) $request->headers->get('Origin', 'http://127.0.0.1:5173'), '/');
            $returnTo = (string) ($request->input('return_url') ?: $request->input('redirect_url') ?: '/my-points/info');
            if (! str_starts_with($returnTo, 'http')) {
                $returnTo = $origin.'/'.ltrim($returnTo, '/');
            }

            return response()->json(['success' => true, 'url' => $returnTo]);
        }

        Configuration::setMerchantId(env('FLITT_PAY_NUMBER'));
        Configuration::setSecretKey(env('FLITT_PAYMENT_KEY'));

        $merchantData = [
            'type' => 'points',
            'user_id' => auth()->user()->id
        ];

        $merchantData = implode(',', $merchantData);

        $checkoutData = [
            'order_id' => time(),
            'order_desc' => 'Bank purchase',
            'server_callback_url' => route('callback.flitt.payment'),
            'response_url' => route('payment.return'),
            'currency' => 'GEL',
            'sender_email' => auth()->user()->email,
            'merchant_data' => $merchantData,
            'amount' => (int)$request->amount * 100
        ];

        $data = Checkout::url($checkoutData);
        $url = $data->getUrl();

        return response()->json(['success' => true, 'url' => $url]);
    }
}
