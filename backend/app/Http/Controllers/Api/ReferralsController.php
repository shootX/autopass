<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferralCodeTemp;
use Illuminate\Http\Request;

class ReferralsController extends Controller
{
    public function list(Request $request)
    {
        $user = $request->user();
        $referrals = $user->referrals()->select('id', 'name', 'created_at')->get()
            ->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'name' => $referral->name,
                    'created_at' => $referral->created_at
                ];
            });
        return response()->json(['success' => true, 'referrals' => $referrals]);
    }

    public function getIp(Request $request)
    {
        $ipAddr = $request->getClientIp();
        return response()->json(['success' => true, 'ip' => $ipAddr]);
    }

    public function handleRef(Request $request, $code)
    {
        // 1. Определяем ОС устройства

        $userAgent = $request->userAgent();

        if (preg_match('/android/i', $userAgent)) {
            $os = 'android';
            $storeUrl = 'https://play.google.com/store/apps/details?id=org.telegram.messenger';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $os = 'ios';
            $storeUrl = 'https://apps.apple.com/app/id686449807';
        } else {
            $os = 'desktop';
            $storeUrl = 'https://geocar.ge';
        }

        $appSchemeUrl = "geocar://ref?code={$code}";

        return view('referral.redirect', compact('storeUrl', 'appSchemeUrl', 'os', 'code'));
    }

    public function saveHash(Request $request)
    {
        ReferralCodeTemp::create([
            'hash' => $request->input('hash'),
            'ref_code' => $request->input('code')
        ]);
        return response()->json(['success' => true]);
    }

    public function checkHash(Request $request)
    {
        $hash = $request->input('hash');
        $check = ReferralCodeTemp::query()->where('hash', $hash)->latest()->first();
        if($check)
        {
            $checkCode = $check->ref_code;
        }
        return response()->json(['success' => true, 'code' => $checkCode ?? null]);
    }
}
