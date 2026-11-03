<?php

namespace App\Models;

use App\Models\Concerns\HasBodyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCar extends Model
{
    use HasBodyStatus;

    // A removed car is only hidden: its bought packages and wash records stay with it.
    use SoftDeletes;

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
        'car_type',
        'body_status',
        'body_options',
        'body_incomplete',
        'body_source',
        'body_checked_at',
        'photo',
        'fuel_type',
        'vin',
        'color',
    ];

    /** Codes stored in user_cars.fuel_type; labels live in lang/{ka,en,ru}/cars.php and in the apps. */
    public const FUEL_TYPES = ['regular', 'premium', 'super', 'diesel', 'electric', 'gas_petrol', 'cng', 'lpg', 'hydrogen'];

    /** A VIN as the tech passport prints it: 17 letters and digits, never I, O or Q. */
    public const VIN_PATTERN = '/^[A-HJ-NPR-Z0-9]{17}$/';

    protected $casts = ['year' => 'integer', 'generation_id' => 'integer', 'modification_id' => 'integer'];

    /** The catalog photo when one was saved, else the model, brand or body-type image. */
    public function imageUrl(): ?string
    {
        return $this->photo ? url($this->photo) : $this->model?->image;
    }

    /** The body type prices and packages go by: only a confirmed one, never the model's guess. */
    public function carType(): ?string
    {
        return $this->bodyConfirmed() ? $this->car_type : null;
    }

    public function trimPayload(): array
    {
        return [
            'generation' => $this->generation_id ? ['id' => $this->generation_id, 'name' => $this->generation_name] : null,
            'modification' => $this->modification_id ? ['id' => $this->modification_id, 'name' => $this->modification_name] : null,
            'body_type' => $this->body_type,
            // Our body type key (sedan, suv, …) that packages and company discounts use; null until confirmed.
            'car_type' => $this->carType(),
            'fuel_type' => $this->fuel_type,
            'vin' => $this->vin,
            'color' => $this->color,
        ] + $this->bodyPayload();
    }

    /** The car in the apps' shape: GET /mycars/{id}, add, and the body pick. */
    public function appPayload(): array
    {
        $this->loadMissing('model.brand');

        return ['id' => $this->id, 'plate' => $this->plate, 'year' => $this->year,
            'model' => $this->model, 'brand' => $this->model?->brand, 'image' => $this->imageUrl()] + $this->trimPayload();
    }

    /** A package still running, or a package payment of the last 30 minutes, was priced by this car's body type. */
    public function holdsPackage(): bool
    {
        $running = UserPackage::query()->where('user_car_id', $this->id)->where('end_date', '>', now())
            ->withWashesLeft()->exists();

        return $running || TbcPayment::query()->where('type', 'package')->where('status', 'pending')
            ->where('payload->car_id', $this->id)->where('created_at', '>', now()->subMinutes(TbcPayment::CHECKOUT_MINUTES))->exists();
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
