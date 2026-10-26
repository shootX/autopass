<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BodyType extends Model
{
    protected $fillable = [
        'name',
        'name_ka',
        'name_en',
        'sort',
    ];

    protected static ?array $labels = null;

    /** The type's name in the current language; `name` is the key other tables store. */
    public function label(): string
    {
        $label = app()->getLocale() === 'ka' ? $this->name_ka : $this->name_en;

        return $label !== null && $label !== '' ? $label : $this->name;
    }

    /** The label for a stored key, or the key itself when no such type exists. */
    public static function labelFor(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        self::$labels ??= static::query()->get()->keyBy('name')->all();

        return isset(self::$labels[$name]) ? self::$labels[$name]->label() : $name;
    }

    /** key => label for select boxes, in the list's own order. */
    public static function options(): array
    {
        return static::query()->ordered()->get()->mapWithKeys(fn (self $type) => [$type->name => $type->label()])->all();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public static function forgetLabels(): void
    {
        self::$labels = null;
    }
}
