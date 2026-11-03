<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Models\UserPackage;
use App\Support\SessionRevoker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deleting an account from the app (owner's rule, 2026-11-03): the account is signed out everywhere and kept
 * as it is, with its points, packages, cars and history, for RESTORE_DAYS. Signing in with the same phone in
 * that time brings it back; after it, AccountEraser erases the personal data for good (accounts:erase-deleted).
 */
class AccountDeletion
{
    public const RESTORE_DAYS = 90;

    /** @return Carbon when the account will be erased */
    public static function request(User $user)
    {
        DB::transaction(function () use ($user) {
            if ($user->deletion_requested_at === null) {
                $user->forceFill(['deletion_requested_at' => now()])->save();
            }
            // Nobody is coming to bookings still on, no card is charged for a renewal, no push reaches the phone.
            Appointment::query()->where('user_id', $user->id)->whereIn('approved', AppointmentStatus::active())
                ->whereDate('date', '>=', now()->toDateString())
                ->update(['approved' => AppointmentStatus::Cancelled->value]);
            UserPackage::query()->where('user_id', $user->id)->update(['auto_renew' => false]);
            $user->pushTokens()->delete();
            SessionRevoker::user($user);
        });

        return self::eraseAt($user->fresh());
    }

    /** Signed in again within RESTORE_DAYS: the account is back as it was. True when it was waiting. */
    public static function restore(User $user): bool
    {
        if ($user->deletion_requested_at === null) {
            return false;
        }
        $user->forceFill(['deletion_requested_at' => null])->save();

        return true;
    }

    public static function eraseAt(User $user)
    {
        return $user->deletion_requested_at?->copy()->addDays(self::RESTORE_DAYS);
    }

    /** Accounts deleted more than RESTORE_DAYS ago and not erased yet. @return int how many were erased */
    public static function eraseDue(): int
    {
        $erased = 0;
        User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays(self::RESTORE_DAYS))
            ->where('phone', 'not like', 'deleted-%')
            ->orderBy('id')
            ->each(function (User $user) use (&$erased) {
                AccountEraser::erase($user);
                $erased++;
            });

        return $erased;
    }
}
