<?php

namespace App\Support;

use App\Models\CorporateClient;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Kill every live credential of an account: JWTs via version bump, web sessions via the session store. */
class SessionRevoker
{
    public static function user(User $user): void
    {
        $user->forceFill(['token_version' => (int) $user->token_version + 1])->save();
        if (config('session.driver') === 'database' || Schema::hasTable('sessions')) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    public static function partner(Partner $partner): void
    {
        $partner->forceFill(['token_version' => (int) $partner->token_version + 1])->save();
    }

    public static function corporate(CorporateClient $client): void
    {
        $client->forceFill(['session_version' => (int) $client->session_version + 1])->save();
    }
}
