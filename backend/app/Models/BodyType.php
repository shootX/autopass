<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BodyType extends Model
{
    protected $fillable = [
        'name',
        'image',
    ];

    protected static ?array $images = null;

    public static function imageFor(?string $name): ?string
    {
        if (self::$images === null) {
            self::$images = static::query()->pluck('image', 'name')->all();
        }

        $image = self::$images[$name] ?? null;

        return $image !== null && $image !== '' ? $image : null;
    }

    public static function forgetImages(): void
    {
        self::$images = null;
    }
}
