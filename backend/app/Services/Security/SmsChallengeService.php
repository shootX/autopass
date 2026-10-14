<?php

namespace App\Services\Security;

use App\Models\SmsTemp;
use App\Models\User;
use App\Services\Sms\IssuedSms;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsStatus;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SmsChallengeService
{
    public function __construct(
        private SmsDispatcher $dispatcher,
        private ActionGrantService $grants
    ) {}

    public function issue(User $user, string $type, array $context = [], ?int $userVoucherId = null, bool $limitResend = true, ?string $destination = null): IssuedSms
    {
        // One issue per user+purpose at a time: a double tap or two parallel requests can't send two SMS.
        $lock = Cache::lock('sms-issue:'.$user->id.':'.$type, 15);
        if (! $lock->get()) {
            throw new AuthChallengeException('resend', 429);
        }

        try {
            return $this->issueLocked($user, $type, $context, $userVoucherId, $limitResend, $destination);
        } finally {
            $lock->release();
        }
    }

    private function issueLocked(User $user, string $type, array $context, ?int $userVoucherId, bool $limitResend, ?string $destination): IssuedSms
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $publicId = bin2hex(random_bytes(16));

        DB::transaction(function () use ($user, $type, $context, $userVoucherId, $code, $publicId, $limitResend) {
            if ($limitResend) {
                $latest = SmsTemp::query()
                    ->where('user_id', $user->id)
                    ->where('type', $type)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                $wait = (int) config('security.sms_resend_seconds');
                if ($latest && $latest->created_at && $latest->created_at->gt(now()->subSeconds($wait))) {
                    throw new AuthChallengeException('resend', 422);
                }
            }

            SmsTemp::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $this->grants->invalidate($user, $this->grantAction($type));

            SmsTemp::query()->create([
                'type' => $type,
                'user_id' => $user->id,
                'code' => hash('sha256', $code),
                'public_id' => $publicId,
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('security.sms_ttl_minutes')),
                'context' => $context === [] ? null : $context,
                'user_voucher_id' => $userVoucherId,
            ]);
        });

        $destination = Phone::forSms($destination ?: $user->phone);
        $result = $this->dispatcher->send(new SmsMessage(
            $destination,
            $this->message($type, $code),
            $type,
            substr($publicId, 0, 20),
            now()->addMinutes((int) config('security.sms_ttl_minutes')),
        ));

        if ($result->status === SmsStatus::Failed) {
            // Never delivered: drop the challenge so it neither works nor blocks a retry (resend window).
            SmsTemp::query()->where('public_id', $publicId)->delete();
        }

        return new IssuedSms($publicId, $result);
    }

    public function verify(string $publicId, string $code, string $type, ?int $userId = null): SmsTemp
    {
        $publicId = trim($publicId);
        $code = trim($code);
        $row = SmsTemp::query()
            ->where('public_id', $publicId)
            ->where('type', $type)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->first();

        $max = (int) config('security.sms_max_attempts');
        if (! $row || $row->consumed_at || ! $row->expires_at || $row->expires_at->lte(now()) || (int) $row->attempts >= $max) {
            if ($row && (int) $row->attempts >= $max && ! $row->consumed_at) {
                $row->forceFill(['consumed_at' => now()])->save();
            }
            throw new AuthChallengeException('invalid', 422);
        }

        if (! hash_equals((string) $row->code, hash('sha256', $code))) {
            $row->increment('attempts');
            if ((int) $row->fresh()->attempts >= $max) {
                SmsTemp::query()->whereKey($row->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            }
            throw new AuthChallengeException('invalid', 422);
        }

        $consumed = SmsTemp::query()
            ->whereKey($row->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', $max)
            ->update(['consumed_at' => now()]);

        if ($consumed !== 1) {
            throw new AuthChallengeException('invalid', 422);
        }

        return $row->fresh();
    }

    public function verifyAndGrant(string $publicId, string $code, string $type, string $action): string
    {
        $row = $this->verify($publicId, $code, $type);
        $user = $row->user;
        if (! $user) {
            throw new AuthChallengeException('invalid', 422);
        }

        return $this->grants->issue($user, $action);
    }

    private function grantAction(string $type): string
    {
        return match ($type) {
            'verify' => 'set_password',
            'reset' => 'reset_password',
            default => $type,
        };
    }

    private function message(string $type, string $code): string
    {
        return match ($type) {
            'verify' => 'Registration code: '.$code,
            'reset' => 'Change password code: '.$code,
            'change' => 'Phone verify code: '.$code,
            'use_voucher' => 'Voucher use code: '.$code,
            default => 'Code: '.$code,
        };
    }
}
