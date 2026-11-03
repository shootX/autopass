<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer of a partner sold for points. Disabled by the admin (deleted): no longer sold, the bought ones still
 * work until date_to. Past date_to: not sold and the bought ones can't be used (owner's rule, 2026-11-03).
 */
class Voucher extends Model
{
    protected $hidden = ['category_id', 'partner_id', 'created_at', 'updated_at', 'pivot'];

    protected $guarded = [];

    protected $casts = [
        'deleted' => 'boolean',
        'user_limit' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VoucherCategory::class);
    }

    /** Still sold: not disabled and not past its date. */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->where('deleted', false)->notExpired();
    }

    /** Not past date_to (a voucher made before dates were kept has none and runs on). */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('date_to')->orWhereDate('date_to', '>=', now()->toDateString()));
    }

    /** Past its last day: bought ones can't be used any more. */
    public function expired(): bool
    {
        return filled($this->date_to) && substr((string) $this->date_to, 0, 10) < now()->toDateString();
    }

    /**
     * Adds what one user sees in the shop: owned (bought by them in all) and left (how many more they may buy:
     * their user_limit, and the stock while one is set).
     *
     * @param  iterable<Voucher>  $vouchers
     */
    public static function fillForUser(iterable $vouchers, int $userId): void
    {
        $ids = collect($vouchers)->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        $owned = UserVoucher::query()->whereIn('voucher_id', $ids)->where('user_id', $userId)
            ->selectRaw('voucher_id, COUNT(*) as n')->groupBy('voucher_id')->pluck('n', 'voucher_id');
        $sold = UserVoucher::query()->whereIn('voucher_id', $ids)
            ->selectRaw('voucher_id, COUNT(*) as n')->groupBy('voucher_id')->pluck('n', 'voucher_id');
        foreach ($vouchers as $voucher) {
            $mine = (int) ($owned[$voucher->id] ?? 0);
            $voucher->setAttribute('owned', $mine);
            $voucher->setAttribute('left', $voucher->leftFor($mine, (int) ($sold[$voucher->id] ?? 0)));
        }
    }

    /** How many more one user may buy, given how many they own and how many are sold in all. */
    public function leftFor(int $owned, int $sold): int
    {
        $left = max(1, (int) ($this->user_limit ?? 1)) - $owned;
        if ((int) $this->count > 0) {
            $left = min($left, (int) $this->count - $sold);
        }

        return max(0, $left);
    }

    public function getPhotoAttribute($value)
    {
        return asset($value);
    }
}
