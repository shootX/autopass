<?php

namespace App\Http\Controllers\WebHook;

use App\Http\Controllers\Controller;
use App\Models\TbcPayment;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\SettleTbcPayment;
use Illuminate\Http\Request;
use Throwable;

class FlittWebHookController extends Controller
{
    public function callback(Request $request, FlittCheckout $flitt, SettleTbcPayment $settle)
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = $request->all();
        }
        if (isset($payload['response']) && is_array($payload['response'])) {
            $payload = $payload['response'];
        }

        try {
            if (! $flitt->valid($payload)) {
                return response('invalid signature', 400);
            }

            $this->apply($payload, $flitt, $settle);
        } catch (Throwable $e) {
            report($e);

            return response('RETRY', 500);
        }

        return response('OK', 200);
    }

    /** A status from Flitt (signed callback, or asked by the server): settles or closes the payment. */
    public function apply(array $payload, FlittCheckout $flitt, SettleTbcPayment $settle): ?TbcPayment
    {
        return $settle->fromFlitt($payload, $flitt);
    }
}
