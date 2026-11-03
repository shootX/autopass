<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paid packages and wash records are never deleted with a car, a package type, a branch or a manager:
     * cars, packages, package types and branches are soft-deleted, and the foreign keys that used to cascade
     * into bought packages and wash records now refuse the delete instead. A package QR also gets a server
     * expiry (qr_expires_at), so a scanned code can't be used again later.
     */
    private const RESTRICT = [
        'user_packages' => ['user_car_id' => 'user_cars', 'package_id' => 'packages'],
        'package_washes' => ['user_package_id' => 'user_packages', 'car_wash_id' => 'car_washes', 'manager_id' => 'users'],
        'user_cars' => ['model_id' => 'car_models'],
    ];

    public function up(): void
    {
        foreach (['user_cars', 'user_packages', 'packages', 'car_washes'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->softDeletes());
        }
        Schema::table('user_packages', function (Blueprint $table) {
            $table->timestamp('qr_expires_at')->nullable()->after('qr_code');
        });

        $this->foreignKeys('restrict');
    }

    public function down(): void
    {
        $this->foreignKeys('cascade');

        Schema::table('user_packages', fn (Blueprint $table) => $table->dropColumn('qr_expires_at'));
        foreach (['user_cars', 'user_packages', 'packages', 'car_washes'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }

    private function foreignKeys(string $onDelete): void
    {
        foreach (self::RESTRICT as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns, $onDelete) {
                foreach ($columns as $column => $parent) {
                    $table->dropForeign([$column]);
                    $table->foreign($column)->references('id')->on($parent)->onDelete($onDelete);
                }
            });
        }
    }
};
