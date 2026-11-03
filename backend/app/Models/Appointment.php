<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $casts = ['approved' => 'integer'];
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

    /** A booking whose car was washed by QR is done; one already closed (cancelled, expired) stays as it was. */
    public static function completeByWash(?int $id): void
    {
        if ($id === null) {
            return;
        }
        static::query()->whereKey($id)->whereIn('approved', AppointmentStatus::active())
            ->update(['approved' => AppointmentStatus::Completed->value]);
    }

    public function services()
    {
        return $this->belongsToMany(CarWashService::class, 'appointments_services', 'appointment_id', 'car_wash_service_id');
    }

    public function car()
    {
        // Bookings are history: a removed car or branch still shows on them.
        return $this->belongsTo(UserCar::class, 'car_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function washing()
    {
        return $this->belongsTo(CarWash::class, 'car_wash_id')->withTrashed();
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
