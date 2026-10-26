<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageWash extends Model
{
    protected $fillable = ['user_package_id', 'car_wash_id', 'manager_id', 'appointment_id', 'walk_in'];

    protected $casts = ['walk_in' => 'boolean'];

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
