<?php

namespace App\Http\Controllers\Api\Partners;

use App\Http\Controllers\Controller;
use App\Models\SmsTemp;
use App\Models\UserVoucher;
use App\Services\Security\AuthChallengeException;
use App\Services\Security\SmsChallengeService;
use App\Services\Sms\SmsStatus;
use App\Services\Sms\SmsUserMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VouchersController extends Controller
{
    public function checkVoucher(Request $request, SmsChallengeService $sms)
    {
        $validate = Validator::make($request->all(), [
            'code' => 'required|string|min:8|max:8',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'message' => 'Incorrect code']);
        }

        $voucher = UserVoucher::query()->where('code', $request->code)->whereNull('used_at')->first();
        if (! $voucher || ! $voucher->user) {
            return response()->json(['success' => false, 'message' => 'Incorrect code']);
        }

        if (! $this->ownedByPartner($voucher)) {
            // Same answer as an unknown code: a partner can't probe other partners' codes.
            return response()->json(['success' => false, 'message' => 'Incorrect code']);
        }

        // Dedupe: one SMS per voucher+recipient per window. The lock serialises parallel checks;
        // a repeat inside the window gets the still-open challenge back instead of a new SMS.
        $lock = Cache::lock('voucher-check:'.$voucher->id.':'.$voucher->user_id, 15);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'message' => 'Check already in progress'], 429);
        }

        try {
            $window = (int) config('security.voucher_check_dedupe_seconds', 60);
            $open = SmsTemp::query()
                ->where('type', 'use_voucher')
                ->where('user_voucher_id', $voucher->id)
                ->where('user_id', $voucher->user_id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->where('attempts', '<', (int) config('security.sms_max_attempts'))
                ->where('created_at', '>', now()->subSeconds($window))
                ->latest('id')
                ->first();
            if ($open) {
                return response()->json([
                    'success' => true,
                    'temp_code' => $open->public_id,
                    'deduplicated' => true,
                ]);
            }

            $sent = SmsTemp::query()
                ->where('type', 'use_voucher')
                ->where('user_voucher_id', $voucher->id)
                ->where('created_at', '>', now()->subMinutes(10))
                ->count();

            if ($sent >= 2) {
                return response()->json(['success' => false, 'message' => 'SMS limit exceeded. Wait 10 minutes.']);
            }

            try {
                $issued = $sms->issue($voucher->user, 'use_voucher', [], $voucher->id, false);
            } catch (\Throwable $e) {
                Log::warning('voucher_check_failed', ['exception' => $e::class]);

                return response()->json(['success' => false, 'message' => 'Some error'], 500);
            }
        } finally {
            $lock->release();
        }

        if (! $issued->result->ok()) {
            return response()->json([
                'success' => false,
                'delivery' => $issued->result->status->value,
                'temp_code' => $issued->result->status === SmsStatus::Unknown ? $issued->publicId : null,
                'message' => SmsUserMessage::forResult($issued->result),
            ], $issued->result->status === SmsStatus::Unknown ? 503 : 422);
        }

        return response()->json([
            'success' => true,
            'temp_code' => $issued->publicId,
            'delivery' => $issued->result->status->value,
        ]);
    }

    public function useVoucher(Request $request, SmsChallengeService $sms)
    {
        $validate = Validator::make($request->all(), [
            'temp_code' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'message' => 'Incorrect code']);
        }

        // Ownership is checked before the code is consumed, so a foreign partner can't burn it.
        $pending = SmsTemp::query()
            ->where('public_id', (string) $request->temp_code)
            ->where('type', 'use_voucher')
            ->first();
        if ($pending && ! $this->ownedByPartner(UserVoucher::find($pending->user_voucher_id))) {
            return response()->json(['success' => false, 'message' => 'Incorrect SMS code'], 422);
        }

        try {
            $row = $sms->verify((string) $request->temp_code, (string) $request->code, 'use_voucher');
        } catch (AuthChallengeException) {
            return response()->json(['success' => false, 'message' => 'Incorrect SMS code'], 422);
        }

        $partnerId = auth('partners')->id();

        // BE-19: keep the record, mark it used exactly once.
        $result = DB::transaction(function () use ($row, $partnerId) {
            $voucher = UserVoucher::query()->lockForUpdate()->find($row->user_voucher_id);
            if (! $voucher || ! $this->ownedByPartner($voucher)) {
                return 'invalid';
            }
            if ($voucher->used_at !== null) {
                return 'used';
            }
            $voucher->forceFill(['used_at' => now(), 'used_by_partner_id' => $partnerId])->save();

            return 'ok';
        });

        return match ($result) {
            'ok' => response()->json(['success' => true, 'message' => 'You successfully used voucher']),
            'used' => response()->json(['success' => false, 'message' => 'Voucher already used'], 422),
            default => response()->json(['success' => false, 'message' => 'Incorrect SMS code'], 422),
        };
    }

    /** Only the partner the voucher belongs to may check or redeem it. */
    private function ownedByPartner(?UserVoucher $userVoucher): bool
    {
        $partnerId = auth('partners')->id();
        $ownerId = $userVoucher?->voucher?->partner_id;

        return $partnerId !== null && $ownerId !== null && (int) $ownerId === (int) $partnerId;
    }
}
