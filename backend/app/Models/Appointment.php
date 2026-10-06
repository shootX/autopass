<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    //

    protected $fillable = [
        'user_id',
        'car_wash_id',
        'date',
        'time',
        'qr_code',
        'approved',
        'car_id',
    ];

    public function services()
    {
        return $this->belongsToMany(CarWashService::class, 'appointments_services', 'appointment_id', 'car_wash_service_id');
    }

    public function car()
    {
        return $this->belongsTo(UserCar::class, 'car_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function washing()
    {
        return $this->belongsTo(CarWash::class, 'car_wash_id');
    }

    public function getDateFormattedAttribute()
    {
        return Carbon::parse($this->date)->format('d.m.Y');
    }

    public function getServicesListAttribute()
    {
        $services = $this->services;
        $l = [];
        foreach ($services as $service) {
            $l[] = $service->name;
        }
        return implode(', ', $l);
    }
}
