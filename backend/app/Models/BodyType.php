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

    /**
     * Catalog (geoapi.ge) body names → our keys, first match wins. Order matters: "pickup truck" is a
     * pickup, "minivan" and "panel van" are not plain vans, "coupe SUV" is an SUV.
     */
    private const CATALOG_WORDS = [
        'tractor_unit' => ['tractor unit', 'tractor', 'semi truck', 'седельный тягач', 'тягач', 'საწევარი'],
        'chassis_cab' => ['chassis cab', 'cab chassis', 'chassis', 'шасси', 'შასი'],
        'flatbed_truck' => ['flatbed', 'platform', 'dropside', 'бортовой', 'ბორტიანი'],
        'pickup' => ['pickup', 'pick up', 'пикап', 'პიკაპი'],
        'bus' => ['bus', 'minibus', 'microbus', 'coach', 'автобус', 'микроавтобус', 'ავტობუსი', 'მიკროავტობუსი'],
        'panel_van' => ['panel van', 'cargo van', 'delivery van', 'box van', 'цельнометаллический фургон', 'грузовой фургон'],
        'minivan' => ['minivan', 'mini van', 'mpv', 'compact van', 'compactvan', 'people carrier', 'минивэн', 'компактвэн', 'მინივენი'],
        'truck' => ['truck', 'lorry', 'грузовик', 'სატვირთო'],
        'van' => ['van', 'фургон', 'ფურგონი'],
        'suv' => ['suv', 'crossover', 'cuv', 'off road', 'offroad', 'jeep', '4x4', 'внедорожник', 'кроссовер', 'ჯიპი', 'კროსოვერი'],
        'wagon' => ['wagon', 'estate', 'universal', 'touring', 'kombi', 'combi', 'shooting brake', 'avant', 'универсал', 'უნივერსალი'],
        'convertible' => ['convertible', 'cabrio', 'cabriolet', 'roadster', 'spyder', 'spider', 'targa', 'кабриолет', 'родстер', 'კაბრიოლეტი'],
        'liftback' => ['liftback', 'fastback', 'sportback', 'лифтбек', 'ლიფტბექი', 'ლიფტბეკი'],
        'hatchback' => ['hatchback', 'hatch', 'хэтчбек', 'хетчбек', 'ჰეჩბექი', 'ჰეჩბეკი'],
        'coupe' => ['coupe', 'coupé', 'купе', 'კუპე'],
        'sedan' => ['sedan', 'saloon', 'limousine', 'седан', 'სედანი'],
    ];

    /** Our key for a body name from the car catalog, or null when we can't tell. */
    public static function fromCatalog(?string $raw): ?string
    {
        $text = ' '.trim((string) preg_replace('/[^\p{L}0-9]+/u', ' ', mb_strtolower((string) $raw))).' ';
        if (trim($text) === '') {
            return null;
        }
        foreach (self::CATALOG_WORDS as $key => $words) {
            foreach ($words as $word) {
                $word = trim((string) preg_replace('/[^\p{L}0-9]+/u', ' ', $word));
                if (str_contains($text, ' '.$word.' ')) {
                    return $key;
                }
            }
        }

        return null;
    }

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
