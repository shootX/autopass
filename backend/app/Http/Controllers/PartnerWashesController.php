<?php

namespace App\Http\Controllers;

use App\Models\CorporateClient;
use App\Services\CorporateWashes;
use Illuminate\Http\Request;

/** Company panel: the wash pool, the company's packages and the ledger. Crediting stays with the admin. */
class PartnerWashesController extends Controller
{
    public function index(Request $request)
    {
        /** @var CorporateClient $corporate */
        $corporate = $request->attributes->get('corporate');

        return view('partner.washes', [
            'corporate' => $corporate,
            'balance' => CorporateWashes::balance($corporate),
            'packages' => $corporate->packages()->where('is_active', true)->orderBy('washes')->get(),
            'moves' => CorporateWashes::ledger($corporate),
            'manage' => false,
        ]);
    }
}
