<?php

namespace App\Services\Sms;

use App\Models\SmsDispatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class SmsInbox
{
    public static function enabled(): bool
    {
        return config('sms.driver') === 'mock' && config('sms.mock_inbox_enabled') === true;
    }

    public static function messages(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        SmsDispatch::query()
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', now());
            })
            ->whereNotNull('body_encrypted')
            ->delete();

        return SmsDispatch::query()
            ->where('status', SmsStatus::Simulated->value)
            ->where('expires_at', '>', now())
            ->whereNotNull('body_encrypted')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (SmsDispatch $row) {
                try {
                    $text = Crypt::decryptString((string) $row->body_encrypted);
                } catch (\Throwable) {
                    return null;
                }

                return [
                    'phone_mask' => $row->phone_mask,
                    'purpose' => self::purpose($row->purpose),
                    'created_at' => $row->created_at?->toIso8601String(),
                    'expires_at' => $row->expires_at?->toIso8601String(),
                    'text' => $text,
                ];
            })
            ->filter()
            ->values();
    }

    public static function purpose(string $purpose): string
    {
        return match ($purpose) {
            'verify' => 'რეგისტრაცია',
            'reset' => 'პაროლის აღდგენა',
            'change' => 'ტელეფონის შეცვლა',
            'use_voucher' => 'ვაუჩერი',
            default => $purpose,
        };
    }
}
