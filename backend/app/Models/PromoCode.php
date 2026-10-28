<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    public const KIND_DISCOUNT = 'discount';

    public const KIND_POINTS = 'points';

    protected $fillable = ['code', 'kind', 'value', 'max_uses', 'starts_at', 'ends_at', 'active'];

    protected $casts = [
        'value' => 'integer',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function uses(): HasMany
    {
        return $this->hasMany(PromoCodeUse::class);
    }

    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /** Null when the code works now, else the reason key (cars.promo_*). */
    public function problem(?User $user = null): ?string
    {
        if (! $this->active) {
            return 'promo_inactive';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'promo_not_started';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'promo_expired';
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return 'promo_used_up';
        }
        if ($user && $this->uses()->where('user_id', $user->id)->exists()) {
            return 'promo_already_used';
        }

        return null;
    }
}
