<?php

namespace App\Http\Controllers;

use App\Models\CorporateClient;
use App\Support\CorporateWashPanel;
use Illuminate\Http\Request;

/** Company panel: the wash pool, packages, discounts, ledger and wash history. Changes stay with the admin. */
class PartnerWashesController extends Controller
{
    public function index(Request $request)
    {
        /** @var CorporateClient $corporate */
        $corporate = $request->attributes->get('corporate');

        return view('partner.washes', ['corporate' => $corporate] + CorporateWashPanel::data($corporate, false));
    }
}
