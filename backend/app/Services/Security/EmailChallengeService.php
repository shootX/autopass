<?php

namespace App\Services\Security;

use App\Mail\VerificationMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailChallengeService
{
    public function issue(User $user, string $email): void
    {
        $latest = $user->verificationCodes()->latest('id')->first();
        $wait = (int) config('security.sms_resend_seconds');
        if ($latest && $latest->created_at && $latest->created_at->gt(now()->subSeconds($wait))) {
            throw new AuthChallengeException('resend', 422);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $previousEmail = $user->email;
        $previousVerified = $user->email_verified_at;

        DB::transaction(function () use ($user, $email, $code) {
            $user->verificationCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $user->email = $email;
            $user->email_verified_at = null;
            $user->save();
            $user->verificationCodes()->create([
                'code' => null,
                'code_hash' => hash('sha256', $code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('security.sms_ttl_minutes')),
            ]);
        });

        try {
            Mail::to($email)->send(new VerificationMail($code));
        } catch (Throwable $e) {
            $user->email = $previousEmail;
            $user->email_verified_at = $previousVerified;
            $user->save();
            $user->verificationCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);
            Log::warning('email_verification_failed', ['exception' => $e::class]);
            throw new SmsDeliveryException('Email could not be sent');
        }
    }

    public function verify(User $user, string $code): void
    {
        $max = (int) config('security.sms_max_attempts');
        $row = VerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $row || ! $row->expires_at || $row->expires_at->lte(now()) || (int) $row->attempts >= $max) {
            if ($row && (int) $row->attempts >= $max && ! $row->consumed_at) {
                $row->forceFill(['consumed_at' => now()])->save();
            }
            throw new AuthChallengeException('invalid', 422);
        }

        if (! hash_equals((string) $row->code_hash, hash('sha256', trim($code)))) {
            $row->increment('attempts');
            if ((int) $row->fresh()->attempts >= $max) {
                VerificationCode::query()->whereKey($row->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            }
            throw new AuthChallengeException('invalid', 422);
        }

        $verified = false;
        DB::transaction(function () use ($user, $row, $max, &$verified) {
            $consumed = VerificationCode::query()
                ->whereKey($row->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->where('attempts', '<', $max)
                ->update(['consumed_at' => now()]);

            if ($consumed !== 1) {
                return;
            }

            $user->markEmailAsVerified();
            $verified = true;
        });

        if (! $verified) {
            throw new AuthChallengeException('invalid', 422);
        }
    }
}
