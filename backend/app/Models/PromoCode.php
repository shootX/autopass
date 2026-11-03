<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Every unresolved payment reserves its promo use until the provider reports paid or unpaid.
     * Age alone cannot prove an order was not paid while its callback/status API was unavailable.
     */
    public function holds(?User $except = null): Builder
    {
        return TbcPayment::query()
            ->where('type', 'points')
            ->where('status', 'pending')
            ->where('payload->promo_code_id', $this->id)
            ->when($except, fn (Builder $query) => $query->where('user_id', '!=', $except->id));
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
        // Payments still open with this code hold a use each until their payment receives a final answer.
        if ($this->max_uses !== null && $this->used_count + $this->holds($user)->count() >= $this->max_uses) {
            return 'promo_used_up';
        }
        if ($user && $this->uses()->where('user_id', $user->id)->exists()) {
            return 'promo_already_used';
        }

        return null;
    }
}
