<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Flitt\Checkout;
use Flitt\Configuration;

class PaymentsController extends Controller
{
    public function testpay(Request $request)
    {
        Configuration::setMerchantId(env('FLITT_PAY_NUMBER'));
        Configuration::setSecretKey(env('FLITT_PAYMENT_KEY'));

        $checkoutData = [
            'order_id' => time(),
            'order_desc' => route('callback.flitt.payment'),
            'server_callback_url' => '',
            'currency' => 'GEL',
            'sender_email' => auth()->user()->email,
            'merchant_data' => auth()->user()->id,
            'amount' => 1000
        ];

        $data = Checkout::url($checkoutData);
        $url = $data->getUrl();

        echo $url;

    }
}
