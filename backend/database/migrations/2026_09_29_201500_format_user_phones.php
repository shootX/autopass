<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->select(['id', 'phone'])
            ->each(function ($row) {
                $deleted = str_ends_with((string) $row->phone, '-del');
                $base = $deleted ? substr($row->phone, 0, -4) : $row->phone;
                $next = Phone::normalize($base);

                if ($next === null) {
                    return;
                }

                if ($deleted) {
                    $next .= '-del';
                }

                if ($next !== $row->phone) {
                    DB::table('users')->where('id', $row->id)->update(['phone' => $next]);
                }
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->select(['id', 'phone'])
            ->each(function ($row) {
                $deleted = str_ends_with((string) $row->phone, '-del');
                $base = $deleted ? substr($row->phone, 0, -4) : $row->phone;
                $digits = Phone::digits($base);

                if (! preg_match('/^5\d{8}$/', $digits)) {
                    return;
                }

                $next = $deleted ? $digits.'-del' : $digits;
                DB::table('users')->where('id', $row->id)->update(['phone' => $next]);
            });
    }
};
