<?php

namespace App\Services\Points;

use App\Models\TbcPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AppSettings;

/** The inviter gets the admin's percent of a friend's paid package back as points (at the point's price). */
class ReferralReward
{
    public static function forPayment(TbcPayment $payment): int
    {
        $percent = max(0, min(100, (int) AppSettings::get('referral_percent')));
        if ($percent === 0 || $payment->referral_paid || $payment->type !== 'package') {
            return 0;
        }

        $friend = User::query()->find($payment->user_id);
        $inviter = $friend?->referral_id ? User::query()->lockForUpdate()->find($friend->referral_id) : null;
        if (! $inviter || $inviter->ban) {
            return 0;
        }

        $points = (int) floor(((float) $payment->amount * $percent / 100) / PointsPricing::unitPrice());
        $payment->forceFill(['referral_paid' => true])->save();
        if ($points < 1) {
            return 0;
        }

        $inviter->points += $points;
        $inviter->save();
        Transaction::create([
            'user_id' => $inviter->id,
            'amount' => $points,
            'type' => 'income',
            'system' => 'Referral',
            'status' => true,
            'comment' => 'Referral #'.$friend->id,
        ]);

        return $points;
    }
}
