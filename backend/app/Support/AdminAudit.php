<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Append-only admin audit trail. Values are stored masked, never in clear. */
class AdminAudit
{
    public static function record(string $targetType, int $targetId, string $action, ?string $field = null, ?string $old = null, ?string $new = null, ?int $adminId = null): void
    {
        DB::table('admin_audit_logs')->insert([
            'admin_id' => $adminId ?? auth()->id(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'field' => $field,
            'old_value' => self::mask($field, $old),
            'new_value' => self::mask($field, $new),
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    public static function mask(?string $field, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if ($field === 'email' && str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 1).'***@'.$domain;
        }
        $digits = preg_replace('/\D/', '', $value);
        if ($field === 'phone' && $digits !== '') {
            return str_repeat('*', max(strlen($digits) - 2, 0)).substr($digits, -2);
        }

        return '***';
    }
}
