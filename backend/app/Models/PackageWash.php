<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageWash extends Model
{
    protected $fillable = ['user_package_id', 'car_wash_id', 'manager_id', 'appointment_id', 'walk_in',
        'full_price', 'discount_percent', 'to_pay'];

    // full_price, discount_percent and to_pay: discount packages only, what the driver paid on site.
    protected $casts = ['walk_in' => 'boolean', 'full_price' => 'decimal:2', 'discount_percent' => 'integer', 'to_pay' => 'decimal:2'];

    public function userPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'user_package_id');
    }

    public function washing(): BelongsTo
    {
        return $this->belongsTo(CarWash::class, 'car_wash_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
