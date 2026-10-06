<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentsService extends Model
{
    protected $fillable = ['appointment_id', 'car_wash_service_id'];
}
