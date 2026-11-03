<?php

namespace App\Services;

use App\Models\FleetCar;
use App\Models\User;
use App\Models\UserCar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erases an account's personal data and keeps its history. The user row stays for orders, packages and the
 * washes a manager approved (a hard delete would take them along); name, contacts and password go, and the
 * phone becomes a unique tombstone, so the same number can register again. The cars are removed (soft) with
 * their VIN, so the plates are free for a new account; company cars go back to invited for the company to see.
 */
class AccountEraser
{
    public static function erase(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->forceFill([
                'name' => null,
                'surname' => null,
                'email' => null,
                'pending_email' => null,
                'date_of_birth' => null,
                'sex' => null,
                'password' => Str::password(40),
                'token_version' => (int) $user->token_version + 1,
            ])->save();
            // Written past the phone mutator (it would strip this to digits and let tombstones collide
            // or even look like a real number). The id makes it unique; it never matches a phone rule.
            DB::table('users')->where('id', $user->id)->update(['phone' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(6))]);

            // No more pushes to a phone whose account is gone.
            $user->pushTokens()->delete();

            FleetCar::query()->where('user_id', $user->id)->get()->each(function (FleetCar $car) {
                $car->forceFill([
                    'status' => $car->status === FleetCar::STATUS_BLOCKED ? FleetCar::STATUS_BLOCKED : FleetCar::STATUS_INVITED,
                    'user_id' => null,
                    'user_car_id' => null,
                    'activated_at' => null,
                    'driver_consented_at' => null,
                    'qr_code' => null,
                    'qr_expires_at' => null,
                ])->save();
            });

            // Cars removed from the app before are kept hidden for the history: their VIN goes too.
            UserCar::withTrashed()->where('user_id', $user->id)->whereNotNull('vin')->update(['vin' => null]);
            UserCar::query()->where('user_id', $user->id)->get()->each(fn (UserCar $car) => $car->delete());
        });
    }
}
