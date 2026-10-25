<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCar extends Model
{
    //
    protected $fillable = [
        'user_id',
        'model_id',
        'plate',
        'year',
        'generation_id',
        'generation_name',
        'modification_id',
        'modification_name',
        'body_type',
        'photo',
    ];

    protected $casts = ['year' => 'integer', 'generation_id' => 'integer', 'modification_id' => 'integer'];

    /** The catalog photo when one was saved, else the model, brand or body-type image. */
    public function imageUrl(): ?string
    {
        return $this->photo ? url($this->photo) : $this->model?->image;
    }

    public function trimPayload(): array
    {
        return [
            'generation' => $this->generation_id ? ['id' => $this->generation_id, 'name' => $this->generation_name] : null,
            'modification' => $this->modification_id ? ['id' => $this->modification_id, 'name' => $this->modification_name] : null,
            'body_type' => $this->body_type,
        ];
    }

    public function model()
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    public function package()
    {
        return $this->hasOne(UserPackage::class, 'user_car_id');
    }
}
